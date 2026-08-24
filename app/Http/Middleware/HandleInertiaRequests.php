<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Permissions\PermissionResolver;
use App\Support\SidebarMenu;
use Inertia\Inertia;
use Inertia\Middleware;
use Illuminate\Http\Request;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /**
         * Resolve the current company the same way the rest of the app
         * does (currentCompany() helper), falling back to the route's
         * own {company} binding if that container binding hasn't been
         * set yet at this point in the request lifecycle.
         */
        $company = currentCompany();
        if (! $company) {
            $routeCompany = $request->route('company');
            $company = $routeCompany instanceof Company ? $routeCompany : null;
        }

        return array_merge(parent::share($request), [
            'auth' => [
                /**
                 * ⚠️ REAL PERFORMANCE + DISCLOSURE BUG FIXED HERE
                 * (found while investigating "the LG Issuance page is
                 * heavy", 2026-08-24).
                 *
                 * This was `$request->user()` — the whole Eloquent
                 * model, serialized with every relation that happened
                 * to be loaded on it by the time Inertia rendered.
                 * PermissionResolver::grantedKeys() (resolved a few
                 * lines below, on every response) loads the user's
                 * Spatie `roles` and `permissions` relations, so both
                 * rode along into the page payload:
                 *
                 *   auth  119,685 bytes  of a 132,517-byte page
                 *     └ roles        65,055
                 *     └ permissions  45,386
                 *
                 * That is ~90% of EVERY Inertia response in the app —
                 * paid on every single navigation — to send data the
                 * frontend never reads: the only things any component
                 * touches are `user.name`, `user.id` and the
                 * `isSuperAdmin` / `permissions` keys below.
                 *
                 * It also shipped the user's `odoo_username` and
                 * PLAINTEXT `odoo_db_password` columns to the browser
                 * on every page, in a payload any viewer can read.
                 *
                 * Explicit fields, so a column added to `users` later
                 * cannot silently start being published again.
                 */
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                /**
                 * isSuperAdmin() is a plain method, not an Eloquent
                 * attribute/accessor — it never reaches the frontend on
                 * its own when the User model is serialized. Added here
                 * explicitly so the topbar can gate the Company/User
                 * management icon by it.
                 */
                'isSuperAdmin' => PermissionResolver::isSuperAdmin($request->user()),
                /**
                 * The user's role name, so a page can display it. Never
                 * branch on this for authorization — use can() with a
                 * permission key, so roles stay reconfigurable without
                 * touching code.
                 */
                'role' => $request->user()?->getRoleName(),
                /**
                 * Every canonical permission key this user holds, e.g.
                 * ['money_received.view', 'cash_expense.create', …].
                 *
                 * Shared on every response so the frontend `can()`
                 * helper answers instantly with no extra request. This
                 * is ONE cached lookup (Spatie caches the permission
                 * table; PermissionResolver memoises per request), not
                 * a query per check.
                 *
                 * Inertia::always() so it survives partial reloads —
                 * without it a page doing a partial visit would receive
                 * no permissions at all and hide every button.
                 */
                'permissions' => Inertia::always(
                    fn () => PermissionResolver::grantedKeys($request->user())
                ),
            ],
            /**
             * Real logout URL (POST route('logout')) — the topbar needs
             * an actual URL, not a route-name lookup, same convention as
             * every other link passed down from a controller in this
             * app.
             */
            'profileUrl' => route('profile.edit'),
            'logoutUrl' => route('logout'),
            /**
             * Admin shortcuts in the topbar.
             *
             * ⚠️ Was `isSuperAdmin ? [...] : null` — a ROLE check, which
             * meant granting someone `user.view` or `role.view` changed
             * nothing: the wrench menu stayed hidden because the URLs it
             * needs were never sent. Reported as "I gave the person the
             * Administration permissions and the icon still did not
             * appear."
             *
             * Now each entry is present only when the user may actually
             * reach that screen, which is the same key EnforcePermission
             * checks on the route itself — so the menu can no longer
             * disagree with what the server allows.
             */
            'adminUrls' => $request->user() ? array_filter([
                'companies' => PermissionResolver::allows($request->user(), 'company.view')
                    ? route('companySection.index') : null,
                'users' => PermissionResolver::allows($request->user(), 'user.view')
                    ? route('user.index') : null,
                'roles' => PermissionResolver::allows($request->user(), 'role.view')
                    ? route('roles.index') : null,
            ]) : [],
            /**
             * ⚠️ Real bug fixed here (the "notifications don't appear
             * until I navigate elsewhere or reload" / "old and new
             * notification appear together" reports):
             *
             * 1. This was a plain array of closures. Regular shared props
             *    (not wrapped in Inertia::always()) are only resolved on
             *    a FULL Inertia visit — on a partial reload (`only`/
             *    `except`, which this app's own pages use in a few
             *    places for preserveState-style re-visits) Inertia skips
             *    evaluating them entirely, so the freshly-flashed message
             *    simply isn't in that response. It would then sit in the
             *    session, unread, until the next visit that happened to
             *    be a full reload — which is exactly "shows up one
             *    navigation late." Inertia::always() forces it to be
             *    resolved on every single response, no exceptions.
             *
             * 2. AppLayout.vue's toast queue is driven by a Vue `watch`
             *    on flash.success/flash.error, which only fires on a
             *    genuine VALUE change. Two actions in a row that happen
             *    to flash the exact same text (e.g., "Deleted
             *    Successfully" twice) were indistinguishable to that
             *    watcher — the second one silently never toasted. Combined
             *    with bug #1's delay, this is what produced the "old and
             *    new together" look: two separate actions' messages,
             *    each delayed, finally landing on the same subsequent
             *    view. `token` is a fresh value every single time a
             *    message is actually flashed (even if the text repeats),
             *    so AppLayout.vue can watch it instead and never miss
             *    or double up a toast.
             */
            /**
             * Bridge Laravel session flashes into BOTH channels:
             * 1. props.flash — kept for any page still watching it
             * 2. Inertia::flash() — native v3 page.flash / inertia:flash event
             *
             * Controllers overwhelmingly use redirect()->with('success'|'fail').
             * php-flasher used to steal 'success' via flash_bag (now disabled
             * in config/flasher.php); with that fixed, these keys survive to
             * the redirected GET and are shared here.
             */
            'flash' => Inertia::always(function () use ($request) {
                /**
                 * ⚠️ pull(), not get() — this is the "the error toast never
                 * goes away, even after a reload" bug.
                 *
                 * A dozen call sites (the Odoo readers, OdooPayment,
                 * OdooSync) used session()->put('fail', ...) instead of
                 * flash()/->with(). put() writes a PERMANENT session key:
                 * nothing ages it out, and reading it with get() here left
                 * it in place. So one failed Odoo sync re-toasted the same
                 * message on every single page for the rest of the session,
                 * and rode along with unrelated success messages afterwards.
                 * Those call sites now flash properly, and consuming the
                 * keys here means a stray put() anywhere can never wedge a
                 * message on screen again — a message is shown exactly once.
                 *
                 * Safe against non-Inertia responses: this closure only runs
                 * while an Inertia page response is being built, so a Blade
                 * or JSON response never consumes anything.
                 */
                $success = $request->session()->pull('success');
                /**
                 * Both keys are pulled unconditionally — with ?? between two
                 * pull() calls the second never runs when the first hits, and
                 * the loser would be the one left wedged in the session.
                 */
                $fail = $request->session()->pull('fail');
                $legacyError = $request->session()->pull('error');
                $error = $fail ?? $legacyError;
                $token = ($success || $error) ? uniqid('', true) : null;

                if ($success || $error) {
                    Inertia::flash(array_filter([
                        'success' => $success,
                        'error' => $error,
                        'token' => $token,
                    ], fn ($v) => $v !== null && $v !== ''));
                }

                return [
                    'success' => $success,
                    'error' => $error,
                    'token' => $token,
                ];
            }),
            /**
             * Shared globally so every migrated page gets the full
             * sidebar automatically — no per-controller navUrls prop
             * needed anymore. See app/Support/SidebarMenu.php.
             */
            'sidebarMenu' => fn () => SidebarMenu::build($company, $request->user()),
            /**
             * Real notification counts, reusing the exact same
             * App\Notification::formatForMenuItem() logic the
             * original Blade sidebar already uses — not reinvented.
             * Each sub-item gets a real detail_url added here (the
             * original only had a `data-show-notification-modal` type
             * key, since it triggered a Blade-rendered modal already
             * present on the page — Vue needs an actual URL instead).
             */
            'notificationMenu' => fn () => $company && $request->user()
                ? collect(\App\Notification::formatForMenuItem($company))->map(function ($category) use ($company) {
                    $category['submenu'] = collect($category['submenu'])->map(function ($sub) use ($company) {
                        $sub['detail_url'] = route('notifications.detail', ['company' => $company->id, 'type' => $sub['data-show-notification-modal']]);
                        return $sub;
                    })->values();
                    return $category;
                })->values()
                : [],
        ]);
    }
}

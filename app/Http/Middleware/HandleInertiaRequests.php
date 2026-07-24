<?php

namespace App\Http\Middleware;

use App\Models\Company;
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
                'user' => $request->user(),
                /**
                 * isSuperAdmin() is a plain method, not an Eloquent
                 * attribute/accessor — it never reaches the frontend on
                 * its own when the User model is serialized. Added here
                 * explicitly so the topbar can gate the Company/User
                 * management icon by it.
                 */
                'isSuperAdmin' => (bool) $request->user()?->isSuperAdmin(),
            ],
            /**
             * Real logout URL (POST route('logout')) — the topbar needs
             * an actual URL, not a route-name lookup, same convention as
             * every other link passed down from a controller in this
             * app.
             */
            'logoutUrl' => route('logout'),
            /**
             * Only present for a super admin — the topbar's
             * Company/User management icon links straight to these,
             * matching the two pages already migrated
             * (CompanyController::index / UserController::index).
             */
            'superAdminUrls' => $request->user()?->isSuperAdmin() ? [
                'companies' => route('companySection.index'),
                'users' => route('user.index'),
            ] : null,
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
            'flash' => Inertia::always(function () use ($request) {
                $success = $request->session()->get('success');
                // Reads both 'fail' and 'error' — the app overwhelmingly
                // flashes failures under session('fail') (53 controller
                // call sites vs. exactly 1 real use of 'error'), so
                // 'fail' takes priority; kept from the original fix here.
                $error = $request->session()->get('fail') ?? $request->session()->get('error');

                return [
                    'success' => $success,
                    'error' => $error,
                    'token' => ($success || $error) ? uniqid('', true) : null,
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

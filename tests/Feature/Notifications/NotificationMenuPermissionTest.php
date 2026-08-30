<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\User;
use App\Notification;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The notifications bell used to demand a dedicated permission per
 * notification type — names that were never added to PermissionRegistry,
 * so the Roles & Permissions screen could not grant them. Only the
 * originally seeded super-admin held them; every account created since
 * (user #97 among them) opened the bell to "Nothing to show."
 *
 * A notification is an early warning about a record the user can already
 * open, so each gate now falls back to that screen's view permission.
 *
 * Two things must both hold, and the second is the one worth guarding:
 * the bell must FILL for someone who can read the underlying screen, and
 * it must still stay EMPTY for someone who cannot. A fix that simply
 * showed everything to everyone would pass the first half alone.
 *
 * Runs against the development database, like AuthorizationEnforcementTest;
 * skips itself when that database is unreachable.
 */
class NotificationMenuPermissionTest extends TestCase
{
    private ?User $actor = null;

    private ?Company $company = null;

    private array $createdPermissionIds = [];

    /** Screen permission → the bell section it must reveal. */
    private const SCREEN_TO_SECTION = [
        'view customer balances' => 'Customer Invoices',
        'view supplier balances' => 'Supplier Invoices',
        'view money received' => 'Receivable Cheques',
        'view supplier payment' => 'Payable Cheques',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        $this->company = Company::first();

        if (! $this->company) {
            $this->markTestSkipped('Development database has no company to exercise.');
        }

        $user = new User;
        $user->name = 'Notification Bell Test Actor';
        $user->email = 'notif-test-'.bin2hex(random_bytes(6)).'@example.test';
        $user->password = bcrypt('secret-'.bin2hex(random_bytes(8)));
        $user->save();
        $user->companies()->attach($this->company->id);

        $this->actor = $user->fresh();
        $this->flush();
    }

    protected function tearDown(): void
    {
        if ($this->actor) {
            DB::table('companies_users')->where('user_id', $this->actor->id)->delete();
            DB::table('model_has_permissions')->where('model_id', $this->actor->id)->delete();
            User::withoutEvents(fn () => User::where('id', $this->actor->id)->forceDelete());
        }

        if ($this->createdPermissionIds) {
            Permission::whereIn('id', $this->createdPermissionIds)->delete();
        }

        auth()->logout();
        $this->flush();

        parent::tearDown();
    }

    private function flush(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }

    /** @param string[] $names */
    private function grant(array $names): void
    {
        $models = [];

        foreach ($names as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $this->createdPermissionIds[] = $permission->id;
            }

            $models[] = $permission;
        }

        $this->actor->syncPermissions($models);
        $this->flush();
        $this->actor = $this->actor->fresh();
        $this->actor->load('permissions');
    }

    /** @return string[] section titles currently visible in the bell */
    private function sections(): array
    {
        auth()->login($this->actor);
        $menu = Notification::formatForMenuItem($this->company);
        auth()->logout();

        return collect($menu)->pluck('title')->all();
    }

    /* ── the reported bug ──────────────────────────────────────────── */

    public function test_a_user_with_no_permissions_at_all_sees_an_empty_bell(): void
    {
        $this->grant([]);

        $this->assertSame([], $this->sections(),
            'A user granted nothing must not be shown any notification section.');
    }

    /**
     * @dataProvider screenPermissionProvider
     */
    public function test_screen_permission_alone_reveals_its_notification_section(string $screen, string $section): void
    {
        $this->grant([$screen]);

        $this->assertContains($section, $this->sections(),
            "Holding '{$screen}' — and none of the legacy notification permissions — must reveal '{$section}'. "
            .'This is the exact state user #97 was in.');
    }

    public static function screenPermissionProvider(): array
    {
        $cases = [];

        foreach (self::SCREEN_TO_SECTION as $screen => $section) {
            $cases[$section] = [$screen, $section];
        }

        return $cases;
    }

    /* ── the half that proves the fix is not just permissive ───────── */

    /**
     * @dataProvider screenPermissionProvider
     */
    public function test_one_screen_permission_reveals_only_its_own_section(string $screen, string $section): void
    {
        $this->grant([$screen]);
        $visible = $this->sections();

        $this->assertSame([$section], $visible,
            "'{$screen}' must reveal '{$section}' and nothing else, but the bell showed: "
            .(implode(', ', $visible) ?: '(nothing)'));
    }

    public function test_unrelated_permissions_do_not_open_the_bell(): void
    {
        // Precisely what user #97 had for notifications: the permission
        // to manage notification SETTINGS, which is a different screen.
        $this->grant(['view notification settings', 'view contracts']);

        $this->assertSame([], $this->sections(),
            'Managing notification settings is not permission to read notifications.');
    }

    /* ── backward compatibility ────────────────────────────────────── */

    public function test_the_legacy_permission_still_works_on_its_own(): void
    {
        $this->grant(['view customer invoice past due notification']);

        $this->assertContains('Customer Invoices', $this->sections(),
            'The original per-notification permission must keep working for whoever already holds it.');
    }

    /* ── drift guards ──────────────────────────────────────────────── */

    /**
     * Every fallback must name a permission the Roles & Permissions
     * screen can actually grant — otherwise the mapping reintroduces the
     * very bug it fixes, silently.
     */
    public function test_every_fallback_permission_is_grantable_through_the_registry(): void
    {
        $reflection = new \ReflectionClass(Notification::class);
        $gates = $reflection->getConstant('NOTIFICATION_GATES');

        $this->assertIsArray($gates);
        $this->assertNotEmpty($gates);

        $ungrantable = [];

        foreach (array_unique(array_values($gates)) as $screenPermission) {
            // A fallback is grantable when the registry knows it — either as
            // a canonical dotted key, or as a legacy alias of one. Both
            // resolve through PermissionResolver, so both are reachable from
            // the Roles & Permissions screen.
            if (! PermissionRegistry::has($screenPermission)
                && ! PermissionRegistry::isLegacyName($screenPermission)) {
                $ungrantable[] = $screenPermission;
            }
        }

        $this->assertSame([], $ungrantable,
            "These fallback permissions are not in PermissionRegistry, so no role can grant them:\n  "
            .implode("\n  ", $ungrantable));
    }

    /**
     * Every permission the bell asks about must have a fallback. A new
     * notification type added without one is invisible to everybody
     * except the seeded super-admin — which is how this started.
     */
    public function test_every_gate_the_bell_checks_has_a_fallback(): void
    {
        $source = file_get_contents(app_path('Notification.php'));

        $start = strpos($source, 'function getAllTypesFormatted');
        $end = strpos($source, 'function formatForMenuItem');
        $body = substr($source, $start, $end - $start);

        preg_match_all("/userCanSee\(\\\$user,\s*'([^']+)'\)/", $body, $matches);
        $checked = array_unique($matches[1]);

        $this->assertNotEmpty($checked, 'Could not find any permission checks in getAllTypesFormatted().');

        $gates = (new \ReflectionClass(Notification::class))->getConstant('NOTIFICATION_GATES');
        $missing = array_values(array_diff($checked, array_keys($gates)));

        $this->assertSame([], $missing,
            "These notification permissions have no fallback in NOTIFICATION_GATES, so only a\n"
            ."super-admin will ever see them:\n  ".implode("\n  ", $missing));
    }

    /**
     * The raw `$user->can()` form is what caused this: it consults only
     * the ungrantable legacy name.
     */
    public function test_the_bell_never_checks_a_permission_directly(): void
    {
        $source = file_get_contents(app_path('Notification.php'));

        $start = strpos($source, 'function getAllTypesFormatted');
        $end = strpos($source, 'function formatForMenuItem');
        $body = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString('$user->can(', $body,
            'Gates in getAllTypesFormatted() must go through Notification::userCanSee(), '
            .'which honours the fallback map. A direct $user->can() re-creates the empty bell.');
    }
}

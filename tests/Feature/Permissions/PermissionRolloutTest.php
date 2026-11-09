<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use App\Support\Permissions\PermissionRollout;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `permissions:rollout` — a new key reaches the people who already hold the screen it came from,
 * exactly once, and nobody else.
 *
 * Each test decides which keys are "new" by rewriting the ledger inside the test's transaction
 * (rolled back afterwards, see Tests\TestCase), so the result never depends on what the registry
 * happens to declare as pending today.
 *
 * report_bank_charges_analysis.view is carved out of the bank-statement screen
 * (legacy 'view bank statement report'), so report_bank_statement.view is its parent.
 */
class PermissionRolloutTest extends TestCase
{
    private const NEW_KEY = 'report_bank_charges_analysis.view';

    private const PARENT_KEY = 'report_bank_statement.view';

    /** Record every registry key as already handed out, except these. */
    private function onlyPending(array $keys): void
    {
        DB::table(PermissionRollout::TABLE)->delete();

        $rows = collect(PermissionRegistry::keys())
            ->reject(fn ($key) => in_array($key, $keys, true))
            ->map(fn ($key) => ['key' => $key, 'granted_users' => 0, 'rolled_out_at' => now()]);

        foreach ($rows->chunk(200) as $chunk) {
            DB::table(PermissionRollout::TABLE)->insert($chunk->values()->all());
        }
    }

    private function rollout(bool $force = true): void
    {
        $this->artisan('permissions:rollout', $force ? ['--force' => true] : [])->assertSuccessful();
        $this->forgetPermissions();
    }

    private function allows(User $user, string $key): bool
    {
        PermissionResolver::flush($user);

        return PermissionResolver::allows($user->fresh(), $key);
    }

    public function test_a_holder_of_the_parent_screen_receives_the_new_key(): void
    {
        $this->onlyPending([self::NEW_KEY]);
        $holder = $this->makeUser([self::PARENT_KEY]);
        $stranger = $this->makeUser(['cash_expense.view']);

        $this->assertFalse($this->allows($holder, self::NEW_KEY), 'Precondition: the parent alone does not open the new key.');

        $this->rollout();

        $this->assertTrue($this->allows($holder, self::NEW_KEY));
        $this->assertFalse($this->allows($stranger, self::NEW_KEY));
        $this->assertSame(1, DB::table(PermissionRollout::TABLE)->where('key', self::NEW_KEY)->count());
    }

    public function test_a_holder_of_the_old_legacy_name_counts_as_a_parent_too(): void
    {
        $this->onlyPending(['report_lg_expired.view']);
        $user = $this->makeUser(['report_lg_by_bank.view']);

        $this->rollout();

        $this->assertTrue($this->allows($user, 'report_lg_expired.view'));
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->onlyPending([self::NEW_KEY]);
        $holder = $this->makeUser([self::PARENT_KEY]);

        $this->rollout(force: false);

        $this->assertFalse($this->allows($holder, self::NEW_KEY));
        $this->assertSame(0, DB::table(PermissionRollout::TABLE)->where('key', self::NEW_KEY)->count());
    }

    public function test_it_happens_once_so_an_admin_removal_sticks(): void
    {
        $this->onlyPending([self::NEW_KEY]);
        $holder = $this->makeUser([self::PARENT_KEY]);

        $this->rollout();
        $this->assertTrue($this->allows($holder, self::NEW_KEY));

        // An admin takes it away again on the per-user screen.
        $holder->fresh()->revokePermissionTo(self::NEW_KEY);
        $this->forgetPermissions();

        $this->artisan('permissions:rollout', ['--force' => true])
            ->expectsOutputToContain('Nothing pending')
            ->assertSuccessful();
        $this->forgetPermissions();

        $this->assertFalse($this->allows($holder, self::NEW_KEY));
    }

    public function test_keys_already_handed_out_are_never_granted_by_parent(): void
    {
        // odoo_integration.view shares the legacy name 'view financial institutions' with
        // financial_institution.view. A manager holding the latter but not the former was
        // restricted on purpose (that's the case on production), and must stay that way.
        $this->onlyPending([self::NEW_KEY]);
        $restricted = $this->makeUser(['financial_institution.view']);

        $this->rollout();

        $this->assertFalse($this->allows($restricted, 'odoo_integration.view'));
    }

    public function test_super_admins_are_left_alone(): void
    {
        $this->onlyPending([self::NEW_KEY]);
        $admin = $this->makeSuperAdmin();
        $admin->givePermissionTo(self::PARENT_KEY);
        $this->forgetPermissions();

        $this->rollout();

        $this->assertFalse($admin->fresh()->permissions->pluck('name')->contains(self::NEW_KEY));
    }

    public function test_another_new_key_does_not_count_as_a_parent(): void
    {
        // .view and .export are both new; holding one new key must not pull in the other.
        $this->onlyPending([self::NEW_KEY, 'report_bank_charges_analysis.export']);

        $parents = PermissionRollout::parentNamesFor(self::NEW_KEY, PermissionRollout::pendingKeys());

        $this->assertContains(self::PARENT_KEY, $parents);
        $this->assertNotContains('report_bank_charges_analysis.export', $parents);
        $this->assertNotContains(self::NEW_KEY, $parents);
    }
}

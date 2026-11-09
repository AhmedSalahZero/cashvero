<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\Support\AttacksRoutes;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * COMPANY ADMINISTRATION: creating, editing and deleting whole companies
 * ==================================================================
 * The company screens (companySection.*, remove-company) have no company number in their address, so the
 * company-by-company tests never touch them. They are guarded by a permission only (company.update,
 * company.delete...) and the controllers do not check that the user belongs to the company they change.
 * In practice only a Super Admin holds those permissions, but a mistake in a role or a permission edit would
 * then let a normal user rewrite or wipe ANY customer.
 *
 *   SEC-36  A user who is NOT a Super Admin, even holding every permission, must be refused when they view,
 *           edit, delete or "remove" a company they do not belong to.
 *
 * The target is a fresh fake company the attacker does not belong to, made by the test and rolled
 * back with everything else, so nothing real is touched. A Super Admin is used as the control, so "refused" can never just mean
 * "the address was wrong".
 */
#[Group('security')]
class CompanyAdministrationSecurityTest extends TestCase
{
    use AttacksRoutes;

    public function test_a_user_who_is_not_a_super_admin_cannot_manage_a_company_that_is_not_theirs(): void
    {
        $this->bootAttack(plantRecords: false);

        $this->assertTrue(Role::where('name', \App\Models\User::SUPER_ADMIN)->where('guard_name', 'web')->exists(), 'Test set-up problem: the test database has no super-admin role, so the control user cannot be created.');

        // Make sure the all-permissions attacker really holds the company permissions (and is not a super admin).
        $companyPermissions = Permission::where('guard_name', 'web')->where('name', 'like', 'company.%')->pluck('name')->all();
        $this->grantTo($this->attacker, array_merge(
            Permission::where('guard_name', 'web')->pluck('name')->all(),
            $companyPermissions
        ));
        $attacker = $this->reload($this->attacker);

        $this->assertFalse(\App\Support\Permissions\PermissionResolver::isSuperAdmin($attacker), 'Test set-up problem: the attacker is a Super Admin.');

        $superAdmin = $this->scratchUser([$this->companyA->id], [], \App\Models\User::SUPER_ADMIN);

        // A company neither the attacker nor the Super Admin belongs to (rolled back with the test).
        $target = $this->makeCompany();

        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $id = $target->id;
            $newPayload = ['name' => 'Changed by test', 'opening_balance_date' => '2020-01-01', 'systems' => [CASH_VERO]];

            $attacks = [
                'open its edit form' => ['GET', route('companySection.edit', ['companySection' => $id], false), []],
                'edit the company'   => ['PUT', route('companySection.update', ['companySection' => $id], false), $newPayload],
                'delete the company' => ['DELETE', route('companySection.destroy', ['companySection' => $id], false), []],
                'remove the company and all its data' => ['POST', route('remove.company', [], false), ['company_id' => $id]],
            ];

            $leaks = [];
            $verified = 0;

            foreach ($attacks as $what => [$verb, $url, $payload]) {
                // Control: a Super Admin must be able to do it, otherwise the address or form is wrong
                // and "refused" would prove nothing.
                $control = $this->send($verb, $url, $loc, $vo, $payload, null, $superAdmin);

                if ($this->isSafe($control, $loc)) {
                    $this->fail("Test set-up problem: even a Super Admin was refused when trying to {$what} ({$verb} {$url} → HTTP {$control}). The test cannot judge this screen.");
                }

                if ($control >= 500 || $vo) {
                    continue;   // the Super Admin's own request crashed (or the screen does not exist) or was bounced by validation: inconclusive
                }

                $verified++;
                $status = $this->send($verb, $url, $location, $validationOnly, $payload, null, $attacker);

                if (! $this->isSafe($status, $location)) {
                    $leaks[] = "{$verb} {$url}  →  {$this->describe($status, $location)}   (a user who is not a Super Admin tried to {$what})";
                }
            }
        } finally {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
        }

        $this->assertGreaterThan(0, $verified, 'None of the company screens could be exercised even by a Super Admin: nothing was verified.');

        $this->assertSame([], $leaks, Finding::message(
            'SEC-36', 'MEDIUM',
            'A user who is not a Super Admin can manage a company that is not theirs',
            $leaks,
            'These screens are protected by a permission alone. The controllers never check that the user is a Super Admin or a member of that company, so anyone who is ever given company.update or company.delete (a role mistake, a permission edit) can rewrite or erase any customer, including all its data (the "remove company" action deletes every row of the company).',
            'In CompanyController (show, edit, update, destroy) and RemoveCompanyController add: abort_unless($user->isSuperAdmin() || $user->companies->contains(\'id\', $company->id), 403); for create/delete prefer Super Admin only. Keep the permission check as well.'
        )."\n\n{$verified} company actions were verified with a Super Admin and then attempted by the ordinary user.");
    }
}

<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Saving the company edit screen must leave every user's own permissions exactly as they were.
 *
 * Regression: CompanyController::update used to call Company::syncPermissionForAllUser(), left
 * over from the old HAuth model. It kept only the old natural-language names (none has a dot) and
 * then synced, so after any save — logo, Odoo settings, opening date — every user of the company,
 * who hold dotted keys since `permissions:migrate-to-user`, was left with no permissions at all.
 */
class CompanyUpdateKeepsUserPermissionsTest extends TestCase
{
    private function saveCompany(Company $company, User $actor, array $changes = []): TestResponse
    {
        return $this->actingAs($actor)->put(
            route('companySection.update', ['companySection' => $company->id]),
            array_merge([
                'name' => $company->name,
                'main_functional_currency' => $company->main_functional_currency,
                'opening_balance_date' => '2020-01-01',
                'systems' => [CASH_VERO],
            ], $changes)
        );
    }

    /** @return string[] */
    private function heldNames(User $user): array
    {
        return $user->fresh()->permissions->pluck('name')->sort()->values()->all();
    }

    public function test_saving_the_company_keeps_every_users_own_permissions(): void
    {
        $company = $this->makeCompany();
        $accountant = $this->makeUser(['cash_expense.view', 'cash_expense.create', 'report_bank_statement.view'], $company);
        $viewer = $this->makeUser(['cash_expense.view'], $company);
        $admin = $this->makeSuperAdmin($company);

        $before = [$accountant->id => $this->heldNames($accountant), $viewer->id => $this->heldNames($viewer)];

        $this->saveCompany($company, $admin)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before[$accountant->id], $this->heldNames($accountant));
        $this->assertSame($before[$viewer->id], $this->heldNames($viewer));

        PermissionResolver::flush();
        $this->assertTrue(PermissionResolver::allows($accountant->fresh(), 'cash_expense.create'));
        $this->assertFalse(PermissionResolver::allows($viewer->fresh(), 'cash_expense.create'));
    }

    public function test_an_old_permission_name_and_dotted_keys_both_survive_a_save(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser(['view bank statement report', 'cash_expense.view'], $company);
        $admin = $this->makeSuperAdmin($company);

        $before = $this->heldNames($user);
        $this->assertContains('view bank statement report', $before, 'Precondition: the user holds the old name.');

        $this->saveCompany($company, $admin)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before, $this->heldNames($user));
    }

    public function test_users_of_another_company_are_untouched(): void
    {
        $company = $this->makeCompany();
        $other = $this->makeCompany();
        $outsider = $this->makeUser(['cash_expense.view', 'report_bank_statement.view'], $other);
        $admin = $this->makeSuperAdmin($company);

        $before = $this->heldNames($outsider);

        $this->saveCompany($company, $admin)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before, $this->heldNames($outsider));
    }

    public function test_the_company_fields_and_systems_are_still_saved(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeSuperAdmin($company);

        $this->saveCompany($company, $admin, ['name' => ['en' => 'Renamed Co', 'ar' => 'Renamed Co']])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Co', $company->fresh()->name['en']);
        $this->assertSame(
            [CASH_VERO],
            DB::table('company_systems')->where('company_id', $company->id)->pluck('system_name')->all()
        );
    }

    /** The systems rows are deleted and rebuilt from the list, so a save without it must write nothing. */
    public function test_a_save_without_the_systems_list_is_refused_and_changes_nothing(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeSuperAdmin($company);

        $payload = [
            'name' => ['en' => 'Should Not Save', 'ar' => 'Should Not Save'],
            'main_functional_currency' => $company->main_functional_currency,
            'opening_balance_date' => '2020-01-01',
        ];

        $this->actingAs($admin)
            ->put(route('companySection.update', ['companySection' => $company->id]), $payload)
            ->assertSessionHasErrors('systems');

        $this->assertNotSame('Should Not Save', $company->fresh()->name['en']);
        $this->assertSame(
            [CASH_VERO],
            DB::table('company_systems')->where('company_id', $company->id)->pluck('system_name')->all()
        );
    }
}

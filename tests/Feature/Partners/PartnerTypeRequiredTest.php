<?php

namespace Tests\Feature\Partners;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Creating/updating a partner without any type flag must be rejected.
 */
class PartnerTypeRequiredTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', env('DB_DATABASE', 'cashvero'))]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database has no financial institution.');
        }

        $this->company = Company::findOrFail($bank->company_id);

        /** @var User $user */
        $user = User::create([
            'name' => 'Partner Type Test '.uniqid(),
            'email' => 'partner-type-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo([
            'customer.create',
            'customer.update',
        ]);
        $user->companies()->attach($this->company->id);
        $user->load('companies');
        $this->user = $user;
    }

    public function test_store_rejects_a_partner_with_no_type_selected(): void
    {
        $name = 'No Type Partner '.uniqid();

        $response = $this->actingAs($this->user)
            ->from(route('partners.create', ['company' => $this->company->id]))
            ->post(route('partners.store', ['company' => $this->company->id]), [
                'name' => $name,
                'is_customer' => false,
                'is_supplier' => false,
                'is_employee' => false,
                'is_subsidiary_company' => false,
                'is_other_partner' => false,
                'is_shareholder' => false,
            ]);

        $response->assertSessionHasErrors('partner_type');
        $this->assertDatabaseMissing('partners', [
            'company_id' => $this->company->id,
            'name' => $name,
        ]);
    }

    public function test_store_accepts_a_partner_with_one_type_selected(): void
    {
        $name = 'Typed Partner '.uniqid();

        $response = $this->actingAs($this->user)
            ->post(route('partners.store', ['company' => $this->company->id]), [
                'name' => $name,
                'is_customer' => true,
                'is_supplier' => false,
                'is_employee' => false,
                'is_subsidiary_company' => false,
                'is_other_partner' => false,
                'is_shareholder' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partners', [
            'company_id' => $this->company->id,
            'name' => $name,
            'is_customer' => 1,
        ]);
    }

    public function test_update_rejects_clearing_every_type(): void
    {
        $partner = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Clear Types '.uniqid(),
            'is_customer' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('partners.edit', ['company' => $this->company->id, 'partner' => $partner->id]))
            ->put(route('partners.update', ['company' => $this->company->id, 'partner' => $partner->id]), [
                'name' => $partner->name,
                'is_customer' => false,
                'is_supplier' => false,
                'is_employee' => false,
                'is_subsidiary_company' => false,
                'is_other_partner' => false,
                'is_shareholder' => false,
            ]);

        $response->assertSessionHasErrors('partner_type');
        $this->assertSame(1, (int) $partner->fresh()->is_customer);
    }
}

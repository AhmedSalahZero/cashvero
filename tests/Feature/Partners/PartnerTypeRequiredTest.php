<?php

namespace Tests\Feature\Partners;

use App\Models\Company;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Creating/updating a partner without any type flag must be rejected.
 *
 * The company, the user and the partner are made by the test.
 */
class PartnerTypeRequiredTest extends TestCase
{
    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeCompany();
        $this->user = $this->makeUser(['customer.create', 'customer.update'], $this->company);
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
        $partner = PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => 'Clear Types '.uniqid()]);

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

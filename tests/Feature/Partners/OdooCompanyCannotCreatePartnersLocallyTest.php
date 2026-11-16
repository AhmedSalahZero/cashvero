<?php

namespace Tests\Feature\Partners;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A company linked to Odoo gets its partners from Odoo only.
 *
 * A partner made locally has no odoo_id, and "Read Odoo Partners"
 * matches on odoo_id alone, so it never gets linked — and the first
 * time it is sent to Odoo (an LG's cash cover, say) the call breaks.
 *
 * The user here deliberately has NO Odoo credentials: the block is on
 * the company, so a user without them cannot make a partner that breaks
 * for a colleague who has them.
 */
class OdooCompanyCannotCreatePartnersLocallyTest extends TestCase
{
    private const CREATE_ROUTES = ['add.new.partner', 'add.new.partner.type', 'partners.create', 'partners.store'];

    private const PERMISSIONS = ['customer.view', 'customer.create', 'supplier.create'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function odooCompany(): Company
    {
        return $this->makeCompany([
            'odoo_db_url' => 'https://odoo.invalid',
            'odoo_db_name' => 'test',
            'odoo_integration_start_date' => '2025-11-01',
        ]);
    }

    private function userWithoutOdoo(Company $company): User
    {
        $user = $this->makeUser(self::PERMISSIONS, $company);
        $this->assertFalse($company->hasOdooIntegrationCredentials($user), 'The user must have no Odoo credentials of their own.');

        return $user;
    }

    private function message(): string
    {
        return __('This company is linked to Odoo. Partners can only be added in Odoo and then loaded with "Read Odoo Partners".');
    }

    private function inertiaStore(Company $company, string $name)
    {
        return $this->actingAs($this->userWithoutOdoo($company))
            ->from(route('partners.index', ['company' => $company->id]))
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('partners.store', ['company' => $company->id]), [
                'name' => $name,
                'is_customer' => true,
                'is_supplier' => false,
                'is_employee' => false,
                'is_subsidiary_company' => false,
                'is_other_partner' => false,
                'is_shareholder' => false,
            ]);
    }

    /**
     * The two add-new endpoints the old forms called with jQuery.
     */
    public static function addNewProvider(): array
    {
        return [
            'add.new.partner' => ['add.new.partner', []],
            'add.new.partner.type' => ['add.new.partner.type', ['type' => 'Customer']],
        ];
    }

    private function ajaxAddNew(Company $company, string $routeName, array $routeParameters, string $name)
    {
        return $this->actingAs($this->userWithoutOdoo($company))
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route($routeName, ['company' => $company->id] + $routeParameters), [
                'customerName' => $name,
                'type' => 'Customer',
            ]);
    }

    private function partnerExists(Company $company, string $name): bool
    {
        return $company->partners()->where('name', $name)->exists();
    }

    // ---------------------------------------------------------------
    // a company linked to Odoo
    // ---------------------------------------------------------------

    public function test_the_partners_form_cannot_save_a_partner(): void
    {
        $company = $this->odooCompany();
        $name = 'NTC '.uniqid();

        $this->inertiaStore($company, $name)
            ->assertRedirect(route('partners.index', ['company' => $company->id]))
            ->assertSessionHas('fail', $this->message());

        $this->assertFalse($this->partnerExists($company, $name));
    }

    #[DataProvider('addNewProvider')]
    public function test_add_new_cannot_save_a_partner(string $routeName, array $routeParameters): void
    {
        $company = $this->odooCompany();
        $name = 'Deposit with others '.uniqid();

        $this->ajaxAddNew($company, $routeName, $routeParameters, $name)
            ->assertOk()
            ->assertExactJson(['status' => false, 'message' => $this->message()]);

        $this->assertFalse($this->partnerExists($company, $name));
    }

    public function test_the_create_page_sends_the_user_back(): void
    {
        $company = $this->odooCompany();
        $index = route('partners.index', ['company' => $company->id]);

        $this->actingAs($this->userWithoutOdoo($company))
            ->from($index)
            ->get(route('partners.create', ['company' => $company->id]))
            ->assertRedirect($index)
            ->assertSessionHas('fail', $this->message());
    }

    /**
     * The button reads activePermissions.create — it disappears even for
     * a user without Odoo credentials, who still gets companyHasOdoo = false.
     */
    public function test_the_partners_page_offers_no_create_button(): void
    {
        $company = $this->odooCompany();

        $this->actingAs($this->userWithoutOdoo($company))
            ->get(route('partners.index', ['company' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partners/Index', false)
                ->where('activePermissions.create', false)
                ->where('activePermissions.view', true)
                ->where('companyHasOdoo', false));
    }

    // ---------------------------------------------------------------
    // a company without Odoo keeps working as before
    // ---------------------------------------------------------------

    public function test_without_odoo_the_partners_form_still_saves(): void
    {
        $company = $this->makeCompany();
        $name = 'Local Partner '.uniqid();

        $this->inertiaStore($company, $name)
            ->assertRedirect(route('partners.index', ['company' => $company->id]))
            ->assertSessionHas('success');

        $this->assertTrue($this->partnerExists($company, $name));
    }

    #[DataProvider('addNewProvider')]
    public function test_without_odoo_add_new_still_saves(string $routeName, array $routeParameters): void
    {
        $company = $this->makeCompany();
        $name = 'Local Customer '.uniqid();

        $this->ajaxAddNew($company, $routeName, $routeParameters, $name)
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertTrue($this->partnerExists($company, $name));
    }

    public function test_without_odoo_the_partners_page_keeps_its_create_button(): void
    {
        $company = $this->makeCompany();

        $this->actingAs($this->userWithoutOdoo($company))
            ->get(route('partners.index', ['company' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partners/Index', false)
                ->where('activePermissions.create', true));
    }

    // ---------------------------------------------------------------
    // a new route cannot forget it
    // ---------------------------------------------------------------

    public function test_every_route_that_creates_a_partner_carries_the_guard(): void
    {
        foreach (self::CREATE_ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} is missing.");
            $this->assertContains('noLocalPartnersForOdoo', $route->gatherMiddleware(), "Route {$name} lets an Odoo company create a partner locally.");
        }
    }
}

<?php

namespace Tests\Feature\InvoiceUpload;

use App\Models\Company;
use App\Models\CustomizedFieldsExportation;
use App\Models\User;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * After a customer-invoice upload is saved, the user should land on
 * Customer Balances — not the invoice upload table.
 *
 * The company, its chosen invoice columns and the user are made by the test.
 */
class CustomerInvoiceImportRedirectsToBalancesTest extends TestCase
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
        $this->user = $this->makeUser([
            'customer_invoice_data.import',
            'customer_invoice_data.view',
            'customer_balance.view',
        ], $this->company);

        // The import page needs the company to have chosen its invoice columns first.
        CustomizedFieldsExportation::create([
            'company_id' => $this->company->id,
            'model_name' => 'CustomerInvoice',
            'fields' => ['customer_name', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount', 'vat_amount'],
        ]);
    }

    public function test_saving_customer_invoices_redirects_to_customer_balances_once_the_job_has_finished(): void
    {
        $this->actingAs($this->user)
            ->post(route('salesGatheringTest.insertToMainTable', [
                'company' => $this->company->id,
                'modelName' => 'CustomerInvoice',
            ]))
            ->assertRedirect(route('view.balances', [
                'company' => $this->company->id,
                'modelType' => 'CustomerInvoice',
            ]));
    }

    public function test_the_import_page_tells_the_client_to_visit_customer_balances_after_save(): void
    {
        $this->actingAs($this->user)
            ->get(route('salesGatheringImport', [
                'company' => $this->company->id,
                'model' => 'CustomerInvoice',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('InvoiceUpload/Import', false)
                ->where('redirectUrlAfterSave', route('view.balances', [
                    'company' => $this->company->id,
                    'modelType' => 'CustomerInvoice',
                ]))
            );
    }
}

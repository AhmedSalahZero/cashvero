<?php

namespace Tests\Feature\InvoiceUpload;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * After a customer-invoice upload is saved, the user should land on
 * Customer Balances — not the invoice upload table.
 */
class CustomerInvoiceImportRedirectsToBalancesTest extends TestCase
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

        if (! Schema::connection('mysql')->hasTable('financial_institutions')) {
            $this->markTestSkipped(
                'No development schema on '.DB::connection('mysql')->getDatabaseName().' — set SMOKE_DB to run this suite.'
            );
        }

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database has no financial institution to attach company context to.');
        }

        $this->company = Company::findOrFail($bank->company_id);

        /** @var User $user */
        $user = User::create([
            'name' => 'Invoice Import Redirect Test '.uniqid(),
            'email' => 'invoice-import-redirect-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo([
            'customer_invoice_data.import',
            'customer_invoice_data.view',
            'customer_balance.view',
        ]);
        $user->companies()->attach($this->company->id);
        $user->load('companies');
        $this->user = $user;
    }

    public function test_saving_customer_invoices_redirects_to_customer_balances_once_the_job_has_finished(): void
    {
        $this->actingAs($this->user)
            ->get(route('salesGatheringTest.insertToMainTable', [
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

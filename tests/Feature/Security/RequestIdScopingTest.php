<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * SEC-37 — a record number typed into a request (?contractId=…, a form field) only ever reaches
 * the company's OWN records. Before this, these screens loaded any company's contract by its
 * number and showed its sales and purchase orders, name and customer inside another company.
 *
 * Run: php artisan test --filter=RequestIdScopingTest
 */
#[\PHPUnit\Framework\Attributes\Group('security')]
class RequestIdScopingTest extends TestCase
{
    use MakesInvoices;

    private \App\Models\Company $mine;

    private int $theirContract;

    private int $myContract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class]);

        $this->mine = $this->makeCompany();
        $theirs = $this->makeCompany();

        $this->myContract = $this->contract($this->customer($this->mine), ['name' => 'MY CONTRACT'])->id;
        $this->theirContract = $this->contract($this->customer($theirs), ['name' => 'THEIR CONTRACT'])->id;

        foreach ([[$this->mine, $this->myContract, 'MINE'], [$theirs, $this->theirContract, 'SECRET']] as [$company, $contract, $label]) {
            DB::table('sales_orders')->insert(['company_id' => $company->id, 'contract_id' => $contract, 'so_number' => "SO-{$label}"]);
            DB::table('purchase_orders')->insert(['company_id' => $company->id, 'contract_id' => $contract, 'po_number' => "PO-{$label}"]);
        }

        $this->signInWith(['customer_contract.view', 'supplier_contract.view'], $this->mine);
    }

    private function ask(string $route, int $contractId)
    {
        return $this->getJson(route($route, ['company' => $this->mine->id, 'contractId' => $contractId]));
    }

    public function test_my_own_contract_still_lists_its_orders(): void
    {
        $this->assertStringContainsString('SO-MINE', $this->ask('update.sales.orders.based.on.contract', $this->myContract)->assertOk()->getContent());
        $this->assertStringContainsString('PO-MINE', $this->ask('update.purchase.orders.based.on.contract', $this->myContract)->assertOk()->getContent());
        $this->assertStringContainsString('SO-MINE', $this->ask('get.po.or.so.from.contract', $this->myContract)->assertOk()->getContent());
    }

    /** @return array<string, array{string}> */
    public static function contractLookups(): array
    {
        return [
            'sales orders of a contract' => ['update.sales.orders.based.on.contract'],
            'purchase orders of a contract' => ['update.purchase.orders.based.on.contract'],
            'orders of a contract (generic)' => ['get.po.or.so.from.contract'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('contractLookups')]
    public function test_another_companys_contract_number_shows_nothing_of_it(string $route): void
    {
        $response = $this->ask($route, $this->theirContract);

        $this->assertContains($response->getStatusCode(), [200, 404], 'Unexpected answer: '.$response->getStatusCode());
        $this->assertStringNotContainsString('SECRET', (string) $response->getContent(), "{$route} showed another company's orders");
        $this->assertStringNotContainsString('THEIR CONTRACT', (string) $response->getContent());
    }
}

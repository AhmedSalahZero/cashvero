<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\CashFlowReportController;
use App\Models\Company;
use App\Models\Contract;
use App\Services\Reports\ContractCashFlowBatchBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * ملخّص كل عقد في تقرير الـ Consolidated Cash Flow
 *
 * * ContractCashFlowBatchBuilder كان بيربط $result بالـ reference على
 * * خانة العقد ( $result = &$resultsByContractCode[$code] ) و ما بيفكّهاش
 * * بعد اللوب . اللوب اللي بعده بيعمل $result = $resultsByContractCode[$code]
 * * عادي — و ده مع الـ reference اللي لسه قايمة بيكتب جوه خانة آخر عقد ،
 * * فكل عقد كان بيدوس على بيانات آخر عقد ، و آخر عقد كان بيطلع بأرقام العقد
 * * اللي قبله
 *
 * * التست بيدّي عقدين بمبالغ مختلفة تماما و بيتأكد إن كل واحد جه بأرقامه هو
 */
class ConsolidatedContractSummariesTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::table('contracts')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        DB::beginTransaction();
        $this->inTransaction = true;

        $this->companyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Consolidated Test Co', 'ar' => 'Consolidated Test Co']),
            'main_functional_currency' => 'EGP',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            DB::rollBack();
            $this->inTransaction = false;
        }

        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    private function customerContractWithSalesOrder(string $suffix, float $amount): Contract
    {
        $partnerId = (int) DB::table('partners')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Customer '.$suffix,
            'is_customer' => 1,
            'is_supplier' => 0,
        ]);

        $contractId = (int) DB::table('contracts')->insertGetId([
            'company_id' => $this->companyId,
            'partner_id' => $partnerId,
            'status' => Contract::RUNNING,
            'model_type' => 'Customer',
            'name' => 'Project '.$suffix,
            'code' => 'c-consolidated-'.$suffix.'-'.$this->companyId,
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'amount' => $amount,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);

        DB::table('sales_orders')->insert([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'so_number' => 'SO-'.$suffix,
            'amount' => $amount,
            'start_date_1' => '2026-09-01',
            'end_date_1' => '2026-11-30',
            'execution_percentage_1' => 100,
            'execution_days_1' => 0,
            'collection_days_1' => 0,
        ]);

        return Contract::find($contractId);
    }

    public function test_each_contract_summary_carries_its_own_numbers(): void
    {
        $first = $this->customerContractWithSalesOrder('A', 100000);
        $second = $this->customerContractWithSalesOrder('B', 7000);
        $third = $this->customerContractWithSalesOrder('C', 250);

        $company = Company::find($this->companyId);
        $controller = new CashFlowReportController;

        $request = Request::create('/x', 'GET', [
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'report_interval' => 'monthly',
        ]);
        app()->instance('request', $request);

        $sharedTimeline = $controller->buildSharedTimelineContext($company, $request);
        if (! is_array($sharedTimeline)) {
            $this->markTestSkipped('Today is outside the report window this test builds.');
        }

        $summaries = app(ContractCashFlowBatchBuilder::class)->build(
            $company,
            $request,
            collect([$first, $second, $third]),
            collect([]),
            $sharedTimeline,
            $controller,
        );

        $inflowByContract = [];
        foreach ($summaries as $summary) {
            $inflowByContract[(int) $summary['contract_id']] = array_sum($summary['cash_inflow']);
        }

        $this->assertSame([
            $first->id => 100000.0,
            $second->id => 7000.0,
            $third->id => 250.0,
        ], $inflowByContract);
    }
}

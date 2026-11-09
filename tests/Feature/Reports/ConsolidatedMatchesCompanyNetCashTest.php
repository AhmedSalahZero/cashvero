<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\CashFlowReportController;
use App\Models\Company;
use App\Models\Contract;
use App\Services\Reports\ConsolidatedCashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صافي التدفق في الـ Consolidated لازم يطابق تقرير الشركة — مهما كانت
 * * العملة المختارة .
 *
 * * اختيار العملة في الـ Consolidated وظيفته الوحيدة انه يحدد انهي عقود
 * * تاخد صفوف مستقلة في contractsSection . القسم العام بيفضل شايل
 * * الشركة كلها بالعملة الوظيفية ، و اللي مش داخل في العقود المختارة
 * * بيظهر في Cash In/Out (unallocated) . فالاجمالي مابيتغيرش .
 *
 * * الباج : loadCompanyReport() كانت بتبعت العملات المختارة لصفوف
 * * التوقّع في القسم العام ، فالقسم ده كان بيتضيّق عليها بينما باقي
 * * صفوفه (بنوك ، فواتير ، شيكات) شايلة الشركة كلها — فالاجمالي كان
 * * ناقص و الصافي ما كانش بيطابق . على شركة حقيقية كان الفرق 131 مليون
 * * في شهر واحد لما تختار USD .
 */
class ConsolidatedMatchesCompanyNetCashTest extends TestCase
{
    private int $companyId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // The report refuses a window that does not include today, and the scenario below is
        // dated September 2026 - January 2027. Freeze the clock inside that window so the test
        // does not start failing once the calendar moves past it.
        $this->travelTo(\Carbon\Carbon::parse('2026-09-17 10:00:00'));

        $this->companyId = $this->makeCompany()->id;
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function contractWithSalesOrder(string $suffix, float $amount, string $currency): Contract
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
            'model_type' => defined(Contract::class.'::FOR_CUSTOMER') ? Contract::FOR_CUSTOMER : 'Customer',
            'name' => 'Project '.$suffix,
            'code' => 'c-netmatch-'.$suffix.'-'.$this->companyId,
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'amount' => $amount,
            'currency' => $currency,
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

    /** @return array{0: array<string, float>, 1: array<string, float>} [الصافي , التراكمي] لكل فترة */
    private function companyReportNetAndAccumulated(array $params): array
    {
        $request = Request::create('/x', 'GET', $params);
        app()->instance('request', $request);
        $out = app(CashFlowReportController::class)->result(Company::find($this->companyId), $request, true);
        $result = is_array($out) ? ($out['result'] ?? $out) : [];

        return [
            $result['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [],
            $result['cash_expenses'][__('Accumulated Net Cash (+/-)')]['total'] ?? [],
        ];
    }

    public function test_net_cash_matches_the_company_report_whatever_currency_is_picked(): void
    {
        $egp = $this->contractWithSalesOrder('EGP', 100000, 'EGP');
        $usd = $this->contractWithSalesOrder('USD', 2000, 'USD');

        $window = ['report_interval' => 'monthly', 'start_date' => '09/01/2026', 'end_date' => '01/31/2027'];

        [$reference, $referenceAccumulated] = $this->companyReportNetAndAccumulated($window + ['currency' => 'EGP']);
        $this->assertNotSame([], $reference, 'The company report built no Net Cash row — is today (pinned in setUp) inside the report window?');

        // نفس العقود مختارة في كل الحالات — اللي بيتغير هو منتقي العملة
        $picks = [
            'EGP فقط' => ['EGP'],
            'USD فقط' => ['USD'],
            'الاتنين' => ['EGP', 'USD'],
        ];

        foreach ($picks as $label => $currencies) {
            $request = Request::create('/x', 'GET', $window + [
                'currencies' => $currencies,
                'contract_ids' => [(string) $egp->id, (string) $usd->id],
            ]);
            app()->instance('request', $request);

            $data = (new ConsolidatedCashFlowService)->build(Company::find($this->companyId), $request);

            foreach (array_keys($data['weeks']) as $period) {
                $this->assertEqualsWithDelta(
                    (float) ($reference[$period] ?? 0),
                    (float) ($data['grandTotal']['net_cash'][$period] ?? 0),
                    0.01,
                    "صافي التدفق في {$period} مع اختيار [{$label}] لازم يطابق تقرير الشركة"
                );

                // التراكمي كمان مش الصافي بس — ده اللي المستخدم بيقرأه
                $this->assertEqualsWithDelta(
                    (float) ($referenceAccumulated[$period] ?? 0),
                    (float) ($data['grandTotal']['accumulated_net'][$period] ?? 0),
                    0.01,
                    "التراكمي في {$period} مع اختيار [{$label}] لازم يطابق تقرير الشركة"
                );
            }
        }
    }

    public function test_accumulated_follows_net_cash_for_every_currency_pick(): void
    {
        $this->contractWithSalesOrder('EGP', 100000, 'EGP');
        $usd = $this->contractWithSalesOrder('USD', 2000, 'USD');

        $request = Request::create('/x', 'GET', [
            'report_interval' => 'monthly', 'start_date' => '09/01/2026', 'end_date' => '01/31/2027',
            'currencies' => ['USD'], 'contract_ids' => [(string) $usd->id],
        ]);
        app()->instance('request', $request);

        $data = (new ConsolidatedCashFlowService)->build(Company::find($this->companyId), $request);

        $running = 0.0;
        foreach (array_keys($data['weeks']) as $period) {
            $running += (float) ($data['grandTotal']['net_cash'][$period] ?? 0);
            $this->assertEqualsWithDelta($running, (float) ($data['grandTotal']['accumulated_net'][$period] ?? 0), 0.01,
                "التراكمي في {$period} لازم يكون مجموع الصافي لحد الفترة دي");
        }
    }
}

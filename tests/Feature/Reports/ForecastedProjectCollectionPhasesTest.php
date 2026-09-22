<?php

namespace Tests\Feature\Reports;

use App\Models\Contract;
use App\Models\CustomerInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صف "Forecasted Project Collection" — توزيع قيمة العقد على مراحل
 * * التنفيذ (execution phases) بدل بُكِت واحد
 *
 * * البلاغ : عقد ٢٩٧ (company 107) قيمته مليون موزّعة على تلات مراحل
 * *   50% تنتهي 30/09/2026 + ٣٠ يوم تحصيل
 * *   20% تنتهي 31/10/2026 + ٣٠ يوم تحصيل
 * *   30% تنتهي 30/11/2026 + ٣٠ يوم تحصيل
 * * و التقرير كان بينزّل المليون كله في شهر ١٢ سنة ٢٦ بدل التوزيعة .
 *
 * * السبب : HArr::getLatestNonZeroExecutionKeys() كانت بترجّع مرحلة واحدة
 * * بس — اللي عندها أبعد end_date — و معاها 'amount' اللي هو إجمالي الأمر
 * * مش حصّة المرحلة . بقت getNonZeroExecutionPhases() بترجّع كل المراحل
 * * مرتّبة من الأقدم للأحدث و كل واحدة معاها نصيبها .
 *
 * * قاعدة الخصم (متأكد منها مع صاحب المشروع) : الدفعات المقدمة و الفواتير
 * * المفتوحة بتاكل المراحل من الأقدم للأحدث ، و الباقي بس هو اللي بيفضل
 * * في المراحل المتأخرة في مواعيدها .
 *
 * * كل الداتا هنا بتتعمل جوه transaction و بتترجع في tearDown
 */
class ForecastedProjectCollectionPhasesTest extends TestCase
{
    private const ROW_KEY = 'Forecasted Project Collection';

    private const ROW_LABEL = 'Cust. Phases-Phased Project';

    /** * نفس مدى البلاغ : 01/09/2026 -> 22/03/2027 شهري */
    private const REPORT_START = '2026-09-01';

    private const REPORT_END = '2027-03-22';

    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    private int $customerPartnerId = 0;

    private int $contractCounter = 0;

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
            'name' => json_encode(['en' => 'Phases Test Co', 'ar' => 'Phases Test Co']),
            'main_functional_currency' => 'EGP',
        ]);

        $this->customerPartnerId = (int) DB::table('partners')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Cust. Phases',
            'is_supplier' => 0,
            'is_customer' => 1,
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

    /* ───────────────────────── builders ───────────────────────── */

    private function contract(float $amount, string $endDate = '2026-11-30', array $overrides = []): array
    {
        $code = 'c-phases-'.$this->companyId.'-'.(++$this->contractCounter);

        $id = (int) DB::table('contracts')->insertGetId(array_merge([
            'company_id' => $this->companyId,
            'partner_id' => $this->customerPartnerId,
            'status' => Contract::RUNNING,
            'model_type' => 'Customer',
            'parent_id' => null,
            'name' => 'Phased Project',
            'code' => $code,
            'start_date' => '2026-09-01',
            'end_date' => $endDate,
            'amount' => $amount,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            // * عقد عادي — مسار التنفيذ الشهري ماعندهوش أوامر أصلاً
            'is_monthly_executed' => 0,
        ], $overrides));

        return ['id' => $id, 'code' => $code, 'name' => $overrides['name'] ?? 'Phased Project'];
    }

    /**
     * * عقد بتنفيذ شهري — موجود في cashvero بس . ماعندهوش أوامر خالص ،
     * * و المتبقي منه بيتوزّع بالتساوي على شهوره اللي لسه ما بدأتش .
     */
    private function monthlyContract(float $amount, string $startDate, string $endDate, string $name): array
    {
        return $this->contract($amount, $endDate, [
            'is_monthly_executed' => 1,
            'start_date' => $startDate,
            'name' => $name,
        ]);
    }

    /**
     * * $phases : لستة من [النسبة , تاريخ نهاية المرحلة , أيام التحصيل]
     * * بتتكتب في الخانات من ١ لحد ٥ بالترتيب اللي بتتبعت بيه — الترتيب
     * * ده مقصود في التست اللي بيتأكد إن الحساب بيرتّب بالتاريخ مش بالخانة
     *
     * @param  array<int,array{0:float,1:string,2:int}>  $phases
     */
    private function salesOrder(int $contractId, float $amount, array $phases, string $soNumber = 'SO-PHASES'): int
    {
        $row = [
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'so_number' => $soNumber,
            'amount' => $amount,
        ];

        foreach (array_values($phases) as $offset => [$percentage, $endDate, $collectionDays]) {
            $index = $offset + 1;
            $row['start_date_'.$index] = $endDate;
            $row['end_date_'.$index] = $endDate;
            $row['execution_percentage_'.$index] = $percentage;
            $row['execution_days_'.$index] = 0;
            $row['collection_days_'.$index] = $collectionDays;
        }

        return (int) DB::table('sales_orders')->insertGetId($row);
    }

    /**
     * * net_balance و invoice_status بيتحسبوا من ترايجر على الجدول ، فالتست
     * * بيبعت قيمة الفاتورة و المحصّل و يسيب الترايجر يوصل للرصيد و الحالة
     */
    private function openInvoice(string $contractCode, float $amount, string $soNumber = 'SO-PHASES'): void
    {
        DB::table('customer_invoices')->insert([
            'company_id' => $this->companyId,
            'customer_id' => $this->customerPartnerId,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'contract_code' => $contractCode,
            'sales_order_number' => $soNumber,
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_amount' => $amount,
            'collected_amount' => 0,
            'invoice_date' => '2026-10-01',
            'invoice_due_date' => '2027-02-15',
        ]);
    }

    private function unusedDownPayment(int $contractId, int $salesOrderId, float $balance): void
    {
        DB::table('down_payment_settlements')->insert([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'sales_order_id' => $salesOrderId,
            'customer_id' => $this->customerPartnerId,
            'currency' => 'EGP',
            'down_payment_amount' => $balance,
            'total_down_payment_settlement' => 0,
            'down_payment_balance' => $balance,
        ]);
    }

    /* ───────────────────────── runners ───────────────────────── */

    /** @return array<string,string> تاريخ => المفتاح الشهري في التقرير */
    private function datesWithWeekNumber(string $start = self::REPORT_START): array
    {
        $map = [];
        $cursor = \Carbon\Carbon::make($start);
        $end = \Carbon\Carbon::make(self::REPORT_END);

        while ($cursor->lessThanOrEqualTo($end)) {
            $map[$cursor->format('Y-m-d')] = $cursor->month.'-'.$cursor->year;
            $cursor = $cursor->copy()->addDay();
        }

        return $map;
    }

    /** @return array<string,float> المفتاح الشهري => المبلغ */
    private function buckets(int $contractId, string $start = self::REPORT_START): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        CustomerInvoice::getForecastedProjectCollection(
            $result,
            $start,
            self::REPORT_END,
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber($start),
            $contractId,
            collect([]),
            'EGP',
        );

        $weeks = $result['customers'][self::ROW_KEY][self::ROW_LABEL]['weeks'] ?? [];

        return array_map('floatval', $weeks);
    }

    /**
     * * كل الصفوف الفرعية (اسم الصف => إجماليه) — لازمة للتستات اللي
     * * بتبني أكتر من عقد في نفس الشركة
     *
     * @return array<string,float>
     */
    private function subRows(): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        CustomerInvoice::getForecastedProjectCollection(
            $result,
            self::REPORT_START,
            self::REPORT_END,
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber(),
            null,
            collect([]),
            'EGP',
        );

        $rows = [];
        foreach ($result['customers'][self::ROW_KEY] ?? [] as $label => $value) {
            if ($label === 'total') {
                continue;
            }
            $rows[$label] = round((float) ($value['total'] ?? 0), 2);
        }

        return $rows;
    }

    private function rowTotal(int $contractId): float
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        CustomerInvoice::getForecastedProjectCollection(
            $result,
            self::REPORT_START,
            self::REPORT_END,
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber(),
            $contractId,
            collect([]),
            'EGP',
        );

        return (float) ($result['customers'][self::ROW_KEY][self::ROW_LABEL]['total'] ?? 0);
    }

    /** * التوزيعة اللي في البلاغ بالظبط : 50/20/30 و ٣٠ يوم تحصيل لكل مرحلة */
    private function reportedPhases(): array
    {
        return [
            [50, '2026-09-30', 30],
            [20, '2026-10-31', 30],
            [30, '2026-11-30', 30],
        ];
    }

    /* ───────────── التوزيعة نفسها ───────────── */

    public function test_each_execution_phase_lands_in_its_own_month(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());

        $this->assertSame([
            '10-2026' => 500000.0,
            '11-2026' => 200000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    public function test_the_whole_contract_no_longer_lands_in_the_last_month(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());

        $buckets = $this->buckets($contract['id']);

        $this->assertNotSame(1000000.0, $buckets['12-2026'] ?? 0.0);
        $this->assertSame(1000000.0, array_sum($buckets), 'التوزيعة لازم تفضل مجموعها قيمة العقد');
    }

    public function test_a_single_full_phase_still_lands_in_one_month(): void
    {
        $contract = $this->contract(400000);
        $this->salesOrder($contract['id'], 400000, [[100, '2026-11-30', 30]]);

        $this->assertSame(['12-2026' => 400000.0], $this->buckets($contract['id']));
    }

    public function test_phases_are_ordered_by_end_date_not_by_slot_number(): void
    {
        $contract = $this->contract(1000000);
        // الخانة ١ فيها المرحلة المتأخرة و الخانة ٢ فيها الأقدم
        $this->salesOrder($contract['id'], 1000000, [
            [40, '2026-11-30', 30],
            [60, '2026-09-30', 30],
        ]);

        // الخصم ٦٠٠ ألف المفروض ياكل المرحلة الأقدم (الخانة ٢) بالكامل .
        // لو الترتيب كان بالخانة كان هياكل الـ ٤٠٠ ألف الأول و يفضّل ٢٠٠
        // ألف يخصمهم من الأقدم فتطلع 400,000 في شهر ١٠ و صفر في شهر ١٢
        $this->openInvoice($contract['code'], 600000);

        $this->assertSame([
            '10-2026' => 0.0,
            '12-2026' => 400000.0,
        ], $this->buckets($contract['id']));
    }

    /* ───────────── النسب المكرّرة في الداتا القديمة ───────────── */

    public function test_two_phases_at_one_hundred_percent_do_not_double_the_order(): void
    {
        // في الداتا المتزامنة من أودو فيه أوامر مجموع نسبها ٢٠٠٪ —
        // مرحلتين كل واحدة ١٠٠٪ . لازم تتقسم نصين مش تتضاعف
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, [
            [100, '2026-09-30', 30],
            [100, '2026-11-30', 30],
        ]);

        $this->assertSame([
            '10-2026' => 500000.0,
            '12-2026' => 500000.0,
        ], $this->buckets($contract['id']));
        $this->assertSame(1000000.0, $this->rowTotal($contract['id']));
    }

    public function test_a_phase_with_zero_percentage_is_ignored(): void
    {
        // آخر خانتين في فورم العقد بيتساب فيهم صفر — زي العقد اللي اتبلّغ
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, array_merge($this->reportedPhases(), [
            [0, '2026-09-22', 0],
            [0, '2026-09-22', 0],
        ]));

        $this->assertSame([
            '10-2026' => 500000.0,
            '11-2026' => 200000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    /* ───────────── الخصم من الأقدم للأحدث ───────────── */

    public function test_an_open_invoice_eats_the_oldest_phase_first(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());
        $this->openInvoice($contract['code'], 500000);

        $this->assertSame([
            '10-2026' => 0.0,
            '11-2026' => 200000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    public function test_a_deduction_bigger_than_one_phase_spills_into_the_next(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());
        $this->openInvoice($contract['code'], 600000);

        $this->assertSame([
            '10-2026' => 0.0,
            '11-2026' => 100000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    public function test_an_unused_down_payment_eats_the_oldest_phase_first(): void
    {
        $contract = $this->contract(1000000);
        $salesOrderId = $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());
        $this->unusedDownPayment($contract['id'], $salesOrderId, 500000);

        $this->assertSame([
            '10-2026' => 0.0,
            '11-2026' => 200000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    public function test_a_deduction_covering_the_whole_order_leaves_nothing(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());
        $this->openInvoice($contract['code'], 1200000);

        $this->assertSame([
            '10-2026' => 0.0,
            '11-2026' => 0.0,
            '12-2026' => 0.0,
        ], $this->buckets($contract['id']));
        $this->assertSame(0.0, $this->rowTotal($contract['id']));
    }

    /* ───────────── المراحل اللي بره مدى التقرير ───────────── */

    public function test_a_phase_outside_the_window_is_not_shown(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());

        // التقرير بيبدأ من ١٢/٢٠٢٦ — أول مرحلتين بره المدى
        $this->assertSame(['12-2026' => 300000.0], $this->buckets($contract['id'], '2026-12-01'));
    }

    public function test_a_phase_outside_the_window_still_absorbs_its_share_of_the_deduction(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());
        $this->openInvoice($contract['code'], 500000);

        // الخصم ٥٠٠ ألف بيتاكل بالكامل في المرحلة الأولى اللي بره المدى ،
        // فالمرحلة اللي جوه المدى لازم تفضل كاملة . لو المشي كان على
        // المراحل اللي جوه المدى بس كانت هتطلع صفر
        $this->assertSame(['12-2026' => 300000.0], $this->buckets($contract['id'], '2026-12-01'));
    }

    public function test_an_order_with_no_executed_phase_produces_nothing(): void
    {
        $contract = $this->contract(1000000);
        $this->salesOrder($contract['id'], 1000000, [
            [0, '2026-09-30', 30],
            [0, '2026-11-30', 30],
        ]);

        $this->assertSame([], $this->buckets($contract['id']));
    }

    /* ───────────── العقد اللي بيخلص بعد مدى التقرير ───────────── */

    /**
     * * البلاغ التاني : عقد ٢٩٧ بيخلص 31/07/2027 و التقرير بينتهي
     * * 22/03/2027 ، فالصف كان بيطلع فاضي خالص مع إن كل مراحله بتتحصّل
     * * في أكتوبر و نوفمبر و ديسمبر ٢٦ — كلهم جوه المدى .
     *
     * * السبب كان فلتر على مستوى العقد : where('end_date','<=',$endDate)
     * * يعني "هات العقود اللي بتنتهي جوه المدى بس" . المدى خاصية بتاريخ
     * * تحصيل كل مرحلة مش بتاريخ نهاية العقد .
     */
    public function test_a_contract_ending_after_the_report_window_still_reports_its_phases(): void
    {
        $contract = $this->contract(1000000, '2027-07-31');
        $this->salesOrder($contract['id'], 1000000, $this->reportedPhases());

        $this->assertSame([
            '10-2026' => 500000.0,
            '11-2026' => 200000.0,
            '12-2026' => 300000.0,
        ], $this->buckets($contract['id']));
    }

    public function test_a_contract_ending_long_after_the_window_keeps_only_its_in_window_phases(): void
    {
        $contract = $this->contract(1000000, '2029-12-31');
        $this->salesOrder($contract['id'], 1000000, [
            [50, '2026-09-30', 30],   // 30/10/2026 — جوه المدى
            [50, '2029-06-30', 30],   // 30/07/2029 — بره المدى
        ]);

        $this->assertSame(['10-2026' => 500000.0], $this->buckets($contract['id']));
    }

    public function test_a_contract_whose_phases_are_all_outside_the_window_adds_no_row(): void
    {
        // العقد بقى بيتحمّل مهما كان تاريخ نهايته ، فلازم نتأكد إنه لسه
        // مابيضيفش صف فاضي لما مفيش ولا مرحلة بتتحصّل جوه المدى
        $contract = $this->contract(1000000, '2029-12-31');
        $this->salesOrder($contract['id'], 1000000, [[100, '2029-06-30', 30]]);

        $this->assertSame([], $this->buckets($contract['id']));
    }

    /* ───────── العقود بتنفيذ شهري (cashvero بس) ───────── */

    /**
     * * شيل فلتر تاريخ العقد كان بيمس مسار العقود الشهرية كمان — هو اللي
     * * كان بيختارها بالتقاطع مع الفترة . المفروض السلوك ما يتغيّرش : العقد
     * * الشهري بيتوزّع على شهوره زي ما هو ، لأن الشرائح أصلاً بتتفلتر على
     * * $datesWithWeekNumber جوّه applyMonthlyExecutedContractBalance() .
     */
    public function test_a_monthly_executed_contract_is_still_spread_over_its_months(): void
    {
        $contract = $this->monthlyContract(600000, '2026-09-01', '2027-02-28', 'Monthly Project');

        $rows = $this->subRows();

        $this->assertArrayHasKey('Cust. Phases-Monthly Project', $rows);
        $this->assertGreaterThan(0, $rows['Cust. Phases-Monthly Project']);
    }

    /**
     * * الحالة اللي الفلتر القديم كان بيغطيها بفرع is_monthly_executed=1 :
     * * عقد بيمتد بعد نهاية التقرير بكتير لازم يفضل داخل .
     */
    public function test_a_monthly_contract_running_past_the_window_is_still_included(): void
    {
        $this->monthlyContract(1200000, '2026-09-01', '2027-12-31', 'Long Monthly');

        $rows = $this->subRows();

        $this->assertArrayHasKey('Cust. Phases-Long Monthly', $rows);
        $this->assertGreaterThan(0, $rows['Cust. Phases-Long Monthly']);
    }

    /**
     * * الحارس المقابل : بعد ما شيلنا الفلتر بقت كل العقود بتتحمّل ، فلازم
     * * نتأكد إن العقد الشهري اللي كل شهوره بره الفترة ما بيضيفش صف فاضي .
     */
    public function test_a_monthly_contract_entirely_outside_the_window_adds_no_row(): void
    {
        $this->monthlyContract(600000, '2028-01-01', '2028-12-31', 'Far Monthly');

        $this->assertSame([], $this->subRows());
    }

    /**
     * * العقد الشهري بيعمل continue قبل حلقة الأوامر ، فحتى لو اتعلّق عليه
     * * أمر بمراحل (ما بيحصلش من الفورم) لازم ياخد قيمته من جدول الشهور
     * * مش من توزيعة المراحل .
     */
    public function test_a_monthly_contract_ignores_any_orders_hanging_off_it(): void
    {
        $contract = $this->monthlyContract(600000, '2026-09-01', '2027-02-28', 'Monthly With Order');
        $this->salesOrder($contract['id'], 600000, $this->reportedPhases());

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        CustomerInvoice::getForecastedProjectCollection(
            $result, self::REPORT_START, self::REPORT_END, 'EGP', $this->companyId,
            $this->datesWithWeekNumber(), $contract['id'], collect([]), 'EGP',
        );

        $weeks = $result['customers'][self::ROW_KEY]['Cust. Phases-Monthly With Order']['weeks'] ?? [];

        // توزيعة المراحل كانت هتدّي 500 ألف في 10-2026 بالظبط ؛ جدول
        // الشهور بيدّي أقساط متساوية ، فالرقم ده لازم ما يظهرش
        $this->assertNotSame(500000.0, (float) ($weeks['10-2026'] ?? 0));
        $this->assertGreaterThan(3, count($weeks), 'المتبقي بيتوزّع على شهور العقد مش على 3 مراحل');
    }

    /**
     * * عقد عادي و عقد شهري في نفس الشركة — كل واحد بيمشي في مساره .
     */
    public function test_an_ordinary_and_a_monthly_contract_live_side_by_side(): void
    {
        $ordinary = $this->contract(1000000, '2027-07-31', ['name' => 'Ordinary Project']);
        $this->salesOrder($ordinary['id'], 1000000, $this->reportedPhases());
        $this->monthlyContract(600000, '2026-09-01', '2027-02-28', 'Monthly Project');

        $rows = $this->subRows();

        $this->assertSame(1000000.0, $rows['Cust. Phases-Ordinary Project'] ?? null);
        $this->assertArrayHasKey('Cust. Phases-Monthly Project', $rows);
        $this->assertGreaterThan(0, $rows['Cust. Phases-Monthly Project']);
    }
}

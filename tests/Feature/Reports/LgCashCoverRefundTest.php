<?php

namespace Tests\Feature\Reports;

use App\Models\Contract;
use App\Models\LetterOfGuaranteeIssuance;
use App\Services\Reports\LgCashCoverRefunds;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * رجوع الكاش كفر بتاع خطابات الضمان
 *
 * * البنك بيحجز كاش كفر وقت إصدار الخطاب و بيرجّعه لما الخطاب يخلص.
 * * التقرير كان بيعرف الرد من حركات الإلغاء بس ، و الإلغاء بيتكتب لما
 * * حد يلغي الخطاب بإيده — يعني حدث ماضي دايما. اتأكدنا من الداتا: كل
 * * حركات الإلغاء في الماضي و مفيش ولا واحدة في المستقبل ، في
 * * القاعدتين. فالنتيجة إن الكفر كان بيخرج وقت الإصدار و عمره ما بيرجع
 * * في أي تقرير بيبص لقدام.
 *
 * * الحل بيمشي على نفس سابقة الودايع: بنقرا من جدول الخطابات نفسه ،
 * * الملغي بتاريخ إلغائه و مبلغه الفعلي ، و الشغال بتاريخ انتهائه و
 * * المتبقي من كفره.
 */
class LgCashCoverRefundTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    private int $partnerId = 0;

    private int $contractId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::table('letter_of_guarantee_issuances')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        DB::beginTransaction();
        $this->inTransaction = true;

        $this->companyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'LG Refund Test Co', 'ar' => 'LG Refund Test Co']),
            'main_functional_currency' => 'EGP',
        ]);

        $this->partnerId = (int) DB::table('partners')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Guarantee Customer',
            'is_customer' => 1,
            'is_supplier' => 0,
        ]);

        $this->contractId = (int) DB::table('contracts')->insertGetId([
            'company_id' => $this->companyId,
            'partner_id' => $this->partnerId,
            'status' => 'running',
            'model_type' => 'Customer',
            'name' => 'Guarantee Project',
            'code' => 'c-lg-'.$this->companyId,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'amount' => 100000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
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

    /* ───────────────────────── fixtures ───────────────────────── */

    private function lg(array $attributes = []): int
    {
        return (int) DB::table('letter_of_guarantee_issuances')->insertGetId(array_merge([
            'company_id' => $this->companyId,
            'partner_id' => $this->partnerId,
            'contract_id' => $this->contractId,
            'lg_code' => 'LG-'.uniqid(),
            'lg_type' => 'final-lgs',
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'issuance_date' => '2026-09-10',
            'renewal_date' => '2026-12-15',
            'lg_amount' => 100000,
            'lg_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
        ], $attributes));
    }

    private function cancellation(int $lgId, string $date, float $amount, string $currency = 'EGP'): void
    {
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'type' => LetterOfGuaranteeIssuance::FOR_CANCELLATION,
            'source' => 'lg-facility',
            'financial_institution_id' => 1,
            'lg_type' => 'final-lgs',
            'date' => $date,
            'debit' => 0,
            'credit' => $amount,
            'currency' => $currency,
        ]);
    }

    private function advancePaymentRepaid(int $lgId, float $amount, string $date = '2026-10-01'): void
    {
        DB::table('lg_issuance_advanced_payment_histories')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'amount' => $amount,
            'date' => $date,
        ]);
    }

    /** @return array<string, array{amount: float, date: string, forecast: bool}> */
    private function refunds(string $start = '2026-09-01', string $end = '2027-06-30', ?int $contractId = null): array
    {
        $rows = LgCashCoverRefunds::between($this->companyId, $start, $end, $contractId ?? $this->contractId);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row->lg_code] = [
                'amount' => round((float) $row->total_amount, 2),
                'date' => (string) $row->movement_date,
                'forecast' => (bool) $row->is_forecast,
            ];
        }

        return $byCode;
    }

    /* ──────────── الخطاب الشغال: توقّع في تاريخ الانتهاء ──────────── */

    public function test_a_running_guarantee_refunds_its_cover_on_its_expiry_date(): void
    {
        $this->lg(['lg_code' => 'LG-RUN', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $this->assertSame(
            ['LG-RUN' => ['amount' => 10000.0, 'date' => '2026-12-15', 'forecast' => true]],
            $this->refunds(),
            'ده كان الصف الفاضي: خطاب شغال هينتهي جوّه الفترة و كفره ما كانش بيرجع'
        );
    }

    public function test_a_guarantee_expiring_after_the_window_is_not_refunded_yet(): void
    {
        $this->lg(['lg_code' => 'LG-LATE', 'renewal_date' => '2027-12-31']);

        $this->assertSame([], $this->refunds());
    }

    public function test_a_guarantee_expiring_before_the_window_is_not_refunded_again(): void
    {
        $this->lg(['lg_code' => 'LG-OLD', 'renewal_date' => '2025-01-01']);

        $this->assertSame([], $this->refunds());
    }

    /**
     * * تاريخ الانتهاء بيتحرك لقدام لما الخطاب يتجدّد ، فالتوقّع بيتحرك
     * * معاه لوحده — نفس سلوك الودايع بالظبط
     */
    public function test_renewing_a_guarantee_moves_its_refund_forward(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-RENEW', 'renewal_date' => '2026-12-15']);

        $this->assertSame('2026-12-15', $this->refunds()['LG-RENEW']['date']);

        DB::table('letter_of_guarantee_issuances')->where('id', $lgId)->update(['renewal_date' => '2027-03-15']);

        $this->assertSame('2027-03-15', $this->refunds()['LG-RENEW']['date']);
    }

    /* ──────────── الخطاب الملغي: المبلغ و التاريخ الفعليين ──────────── */

    public function test_a_cancelled_guarantee_keeps_its_actual_date_and_amount(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-CANC', 'renewal_date' => '2027-01-31', 'cash_cover_amount' => 10000]);
        $this->cancellation($lgId, '2026-10-05', 9500);

        $this->assertSame(
            ['LG-CANC' => ['amount' => 9500.0, 'date' => '2026-10-05', 'forecast' => false]],
            $this->refunds(),
            'الملغي بياخد المبلغ اللي رجع فعلا مش المسجّل ، و بتاريخ الإلغاء مش الانتهاء'
        );
    }

    public function test_a_cancelled_guarantee_is_never_counted_twice(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-ONCE', 'renewal_date' => '2027-01-31']);
        $this->cancellation($lgId, '2026-10-05', 10000);

        $rows = LgCashCoverRefunds::between($this->companyId, '2026-09-01', '2027-06-30', $this->contractId);

        $this->assertCount(1, $rows, 'صف واحد لكل خطاب: مرة كملغي أو مرة كمتوقّع ، مش الاتنين');
    }

    /**
     * * خطاب اتلغى و حركته اتمسحت — مش هنخترعله رد مستقبلي. لقينا
     * * الحالة دي فعلا في الداتا
     */
    public function test_a_cancelled_guarantee_without_a_movement_is_not_forecast(): void
    {
        $this->lg([
            'lg_code' => 'LG-GHOST',
            'status' => LetterOfGuaranteeIssuance::CANCELLED,
            'renewal_date' => '2026-12-15',
        ]);

        $this->assertSame([], $this->refunds());
    }

    /* ──────────── خطابات الدفعة المقدمة: التغطية بتتناقص ──────────── */

    public function test_an_advance_payment_guarantee_only_refunds_what_is_left(): void
    {
        $lgId = $this->lg([
            'lg_code' => 'LG-ADV',
            'lg_type' => 'advanced-payment-lgs',
            'lg_amount' => 12819240,
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 1281924,
            'renewal_date' => '2027-03-15',
        ]);
        $this->advancePaymentRepaid($lgId, 8294461.75);

        // (12,819,240 − 8,294,461.75) × 10% = 452,477.83
        $this->assertSame(452477.83, $this->refunds()['LG-ADV']['amount'],
            'المبلغ الأصلي كان هيعرض ١٫٢٨ مليون بدل ٤٥٢ ألف — ٨٢٩ ألف فلوس وهمية');
    }

    public function test_a_fully_repaid_advance_payment_guarantee_refunds_nothing(): void
    {
        $lgId = $this->lg([
            'lg_code' => 'LG-DONE',
            'lg_type' => 'advanced-payment-lgs',
            'lg_amount' => 100000,
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
        ]);
        $this->advancePaymentRepaid($lgId, 100000);

        $this->assertSame([], $this->refunds(), 'التغطية خلصت ، مفيش حاجة ترجع');
    }

    public function test_repaying_more_than_the_guarantee_never_goes_negative(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-OVER', 'lg_amount' => 100000, 'cash_cover_rate' => 10, 'cash_cover_amount' => 10000]);
        $this->advancePaymentRepaid($lgId, 500000);

        $this->assertSame([], $this->refunds(), 'مبلغ سالب كان هيبقى مصروف مش إيراد');
    }

    public function test_several_repayments_are_added_up(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-MULTI', 'lg_amount' => 100000, 'cash_cover_rate' => 10, 'cash_cover_amount' => 10000]);
        $this->advancePaymentRepaid($lgId, 30000, '2026-09-20');
        $this->advancePaymentRepaid($lgId, 20000, '2026-10-20');

        // (100,000 − 50,000) × 10% = 5,000
        $this->assertSame(5000.0, $this->refunds()['LG-MULTI']['amount']);
    }

    /**
     * * الملغي بياخد المبلغ الفعلي زي ما هو ، حتى لو عليه دفعات مسدّدة —
     * * البنك قال رجّع كام خلاص
     */
    public function test_a_cancelled_advance_payment_guarantee_uses_the_real_refund(): void
    {
        $lgId = $this->lg([
            'lg_code' => 'LG-ADVC',
            'lg_type' => 'advanced-payment-lgs',
            'lg_amount' => 100000,
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'renewal_date' => '2027-05-01',
        ]);
        $this->advancePaymentRepaid($lgId, 40000);
        $this->cancellation($lgId, '2026-11-11', 6123.45);

        $this->assertSame(
            ['LG-ADVC' => ['amount' => 6123.45, 'date' => '2026-11-11', 'forecast' => false]],
            $this->refunds()
        );
    }

    /* ──────────── حدود و نطاق ──────────── */

    public function test_a_guarantee_without_cash_cover_refunds_nothing(): void
    {
        $this->lg(['lg_code' => 'LG-NOCOVER', 'cash_cover_amount' => 0, 'cash_cover_rate' => 0]);

        $this->assertSame([], $this->refunds());
    }

    public function test_only_the_requested_contract_is_returned(): void
    {
        $this->lg(['lg_code' => 'LG-MINE']);

        $otherContractId = (int) DB::table('contracts')->insertGetId([
            'company_id' => $this->companyId,
            'partner_id' => $this->partnerId,
            'status' => 'running',
            'model_type' => 'Customer',
            'name' => 'Other',
            'code' => 'c-other-lg-'.$this->companyId,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'amount' => 1,
            'currency' => 'EGP',
        ]);
        $this->lg(['lg_code' => 'LG-THEIRS', 'contract_id' => $otherContractId]);

        $this->assertSame(['LG-MINE'], array_keys($this->refunds()));
    }

    public function test_without_a_contract_every_guarantee_of_the_company_is_returned(): void
    {
        $this->lg(['lg_code' => 'LG-A']);
        $this->lg(['lg_code' => 'LG-B', 'contract_id' => null]);

        $codes = array_keys($this->refunds('2026-09-01', '2027-06-30', 0));
        sort($codes);

        $this->assertSame(['LG-A', 'LG-B'], $codes, 'تقرير الشركة بيشوف الخطابات المش مربوطة بعقد كمان');
    }

    public function test_another_company_is_never_mixed_in(): void
    {
        $this->lg(['lg_code' => 'LG-US']);

        $otherCompanyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Other Co', 'ar' => 'Other Co']),
            'main_functional_currency' => 'EGP',
        ]);
        $this->lg(['lg_code' => 'LG-THEM', 'company_id' => $otherCompanyId, 'contract_id' => null]);

        $this->assertSame(['LG-US'], array_keys($this->refunds('2026-09-01', '2027-06-30', 0)));
    }

    public function test_the_window_edges_are_inclusive(): void
    {
        $this->lg(['lg_code' => 'LG-START', 'renewal_date' => '2026-09-01']);
        $this->lg(['lg_code' => 'LG-END', 'renewal_date' => '2027-06-30']);

        $codes = array_keys($this->refunds('2026-09-01', '2027-06-30'));
        sort($codes);

        $this->assertSame(['LG-END', 'LG-START'], $codes);
    }

    public function test_the_forecast_flag_tells_the_two_apart(): void
    {
        $this->lg(['lg_code' => 'LG-F', 'renewal_date' => '2026-12-15']);
        $cancelledId = $this->lg(['lg_code' => 'LG-R', 'renewal_date' => '2027-02-02']);
        $this->cancellation($cancelledId, '2026-10-10', 10000);

        $refunds = $this->refunds();

        $this->assertTrue($refunds['LG-F']['forecast']);
        $this->assertFalse($refunds['LG-R']['forecast']);
    }

    public function test_the_currency_follows_the_guarantee_then_the_movement(): void
    {
        $this->lg(['lg_code' => 'LG-USD', 'lg_currency' => 'USD', 'renewal_date' => '2026-12-15']);
        $cancelledId = $this->lg(['lg_code' => 'LG-EUR', 'lg_currency' => 'USD', 'renewal_date' => '2027-02-02']);
        $this->cancellation($cancelledId, '2026-10-10', 10000, 'EURO');

        $currencies = [];
        foreach (LgCashCoverRefunds::between($this->companyId, '2026-09-01', '2027-06-30', $this->contractId) as $row) {
            $currencies[$row->lg_code] = $row->currency;
        }

        $this->assertSame('USD', $currencies['LG-USD'], 'المتوقّع بعملة الخطاب');
        $this->assertSame('EURO', $currencies['LG-EUR'], 'و الفعلي بعملة الحركة');
    }

    public function test_both_issuance_categories_are_refunded(): void
    {
        $this->lg(['lg_code' => 'LG-NEW', 'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE]);
        $this->lg(['lg_code' => 'LG-OPEN', 'category_name' => 'opening-balance']);

        $codes = array_keys($this->refunds());
        sort($codes);

        $this->assertSame(['LG-NEW', 'LG-OPEN'], $codes,
            'نفس قاعدة الصف الأصلي: النوعين بيرجّعوا كفرهم');
    }

    /* ──────────── التكامل مع التقرير نفسه ──────────── */

    /**
     * @return array{result: array, dates: array}
     */
    private function runContractReport(string $formStart = '09/01/2026', string $formEnd = '06/30/2027'): array
    {
        $company = \App\Models\Company::find($this->companyId);

        $request = \Illuminate\Http\Request::create('/x', 'GET', [
            'report_interval' => 'monthly',
            'contract_id' => $this->contractId,
            'start_date' => $formStart,
            'end_date' => $formEnd,
        ]);
        app()->instance('request', $request);

        return (new \App\Http\Controllers\CashFlowReportController)->result($company, $request, true, null, 0);
    }

    /** @return array<string, float> */
    private function refundRow(array $report): array
    {
        $row = $report['result']['customers'][__('Cancelled LGs Cash Cover')] ?? [];

        return array_map(
            static fn ($v) => round((float) $v, 2),
            is_array($row['total'] ?? null) ? $row['total'] : []
        );
    }

    public function test_the_report_shows_the_refund_in_the_month_the_guarantee_expires(): void
    {
        $this->lg(['lg_code' => 'LG-REP', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $this->assertSame(['12-2026' => 10000.0], $this->refundRow($this->runContractReport()));
    }

    public function test_the_refund_is_added_to_total_cash_inflow(): void
    {
        $this->lg(['lg_code' => 'LG-IN', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $inflow = $this->runContractReport()['result']['customers'][__('Total Cash Inflow')]['total'] ?? [];

        $this->assertSame(10000.0, round((float) ($inflow['12-2026'] ?? 0), 2));
    }

    /**
     * * الاختبار اللي بيلخّص الحكاية كلها: كفر خرج وقت الإصدار لازم
     * * يرجع بالكامل وقت الانتهاء لو الاتنين جوّه الفترة
     */
    public function test_what_goes_out_at_issuance_comes_back_at_expiry(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-BAL', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);

        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'type' => 'debit-lg-amount',
            'source' => 'lg-facility',
            'financial_institution_id' => 1,
            'lg_type' => 'final-lgs',
            'date' => '2026-09-10',
            'debit' => 7500,
            'credit' => 0,
            'currency' => 'EGP',
        ]);

        $report = $this->runContractReport();

        $issued = $report['result']['cash_expenses'][__('Issued LG Cash Cover')]['total'] ?? [];

        $this->assertSame(7500.0, round((float) ($issued['9-2026'] ?? 0), 2), 'خرج في سبتمبر');
        $this->assertSame(['1-2027' => 7500.0], $this->refundRow($report), 'و رجع في يناير');
        $this->assertSame(
            round(array_sum($issued), 2),
            round(array_sum($this->refundRow($report)), 2),
            'الخارج يساوي الراجع'
        );
    }

    /**
     * * بيانات البوب-اب بتتبني جوّه الـ loader ، فبنناديه على طول بدل
     * * ما نعدّي على شكل الريسبونس (بليد هنا و Inertia في النظام التاني)
     */
    public function test_the_breakdown_popup_names_the_guarantee(): void
    {
        $this->lg(['lg_code' => 'LG-POPUP', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        $letterOfGuaranteeModelData = [];
        $incomingTransferModelData = [];

        \App\Services\Reports\CashFlowContractDetailPeriodBatchLoader::apply(
            $result,
            $letterOfGuaranteeModelData,
            collect([]),
            'EGP',
            $this->companyId,
            'c-lg-'.$this->companyId,
            $this->contractId,
            $this->partnerId,
            '2026-09-01',
            '2027-06-30',
            ['12-2026' => ['start_date' => '2026-12-01', 'end_date' => '2026-12-31']],
            $incomingTransferModelData,
            collect([]),
        );

        $popup = $letterOfGuaranteeModelData[__('Cancelled LGs Cash Cover')] ?? [];

        $entries = [];
        foreach ($popup as $byWeek) {
            foreach ($byWeek['weeks'] ?? [] as $rows) {
                foreach ($rows as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        $this->assertCount(1, $entries);
        $this->assertSame('LG-POPUP', $entries[0]['lg_code']);
        $this->assertSame('Guarantee Customer', $entries[0]['name']);
        $this->assertSame(10000.0, round((float) $entries[0]['amount'], 2));
    }

    /* ──────────── باج العمود الأخير ──────────── */

    /**
     * * فورم تقرير العقد بيستخدم datepicker بيبعت 06/30/2027 ، و
     * * CashFlowWeekBucketer بيقارن التواريخ كنصوص — فـ '2027-06-30'
     * * <= '06/30/2027' بترجع false و أي حركة في آخر عمود كانت بتتسقط
     * * في صمت. مش خاص بخطابات الضمان: كل صفوف التقرير كانت بتتأثر.
     */
    public function test_a_movement_on_the_last_day_is_not_dropped_when_the_form_sends_slashes(): void
    {
        $this->lg(['lg_code' => 'LG-LASTDAY', 'renewal_date' => '2027-06-30', 'cash_cover_amount' => 4321]);

        $this->assertSame(
            ['6-2027' => 4321.0],
            $this->refundRow($this->runContractReport('09/01/2026', '06/30/2027')),
            'آخر يوم في التقرير لازم يتحسب'
        );
    }

    public function test_the_same_movement_works_when_the_form_sends_iso_dates(): void
    {
        $this->lg(['lg_code' => 'LG-ISO', 'renewal_date' => '2027-06-30', 'cash_cover_amount' => 4321]);

        $this->assertSame(
            ['6-2027' => 4321.0],
            $this->refundRow($this->runContractReport('2026-09-01', '2027-06-30'))
        );
    }

    public function test_the_last_period_still_ends_on_the_requested_date(): void
    {
        $this->lg(['lg_code' => 'LG-BEYOND', 'renewal_date' => '2027-07-05', 'cash_cover_amount' => 999]);

        $this->assertSame([], $this->refundRow($this->runContractReport('09/01/2026', '06/30/2027')),
            'اللي بعد نهاية التقرير يفضل بره');
    }

    /* ──────────── تقرير الشركة ──────────── */

    /**
     * @return array<string, float>
     */
    private function companyRefundRow(string $reportCurrency = 'EGP'): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        $letterOfGuaranteeModelData = [];
        $incomingTransferModelData = [];
        $crossCurrencyNotes = [];

        \App\Services\Reports\CashFlowCompanyPeriodBatchLoader::apply(
            $result,
            \App\Models\ForeignExchangeRate::where('company_id', $this->companyId)->get(),
            'EGP',
            $this->companyId,
            '2026-09-01',
            '2027-06-30',
            ['12-2026' => ['start_date' => '2026-12-01', 'end_date' => '2026-12-31']],
            $letterOfGuaranteeModelData,
            $reportCurrency,
            $incomingTransferModelData,
            $crossCurrencyNotes,
        );

        $row = $result['customers'][__('Cancelled LGs Cash Cover')] ?? [];

        return array_map(
            static fn ($v) => round((float) $v, 2),
            is_array($row['total'] ?? null) ? $row['total'] : []
        );
    }

    public function test_the_company_report_also_refunds_a_running_guarantee(): void
    {
        $this->lg(['lg_code' => 'LG-CO', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $this->assertSame(['12-2026' => 10000.0], $this->companyRefundRow(),
            'التقريرين لازم يتفقوا على نفس الشركة');
    }

    public function test_the_company_report_sees_guarantees_with_no_contract(): void
    {
        $this->lg(['lg_code' => 'LG-NOCONTRACT', 'contract_id' => null, 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 2500]);

        $this->assertSame(['12-2026' => 2500.0], $this->companyRefundRow());
    }

    public function test_the_company_report_keeps_a_cancelled_guarantee_as_is(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-COCANC', 'renewal_date' => '2027-05-05']);
        $this->cancellation($lgId, '2026-12-20', 7777);

        $this->assertSame(['12-2026' => 7777.0], $this->companyRefundRow());
    }

    /**
     * * تبويب العملة الأجنبية بيعرض أرقام العملة دي لوحدها من غير تحويل
     */
    public function test_a_foreign_currency_tab_only_shows_that_currency(): void
    {
        $this->lg(['lg_code' => 'LG-EGP', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000, 'lg_currency' => 'EGP']);
        $this->lg(['lg_code' => 'LG-USDONLY', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 400, 'lg_currency' => 'USD']);

        $this->assertSame(['12-2026' => 400.0], $this->companyRefundRow('USD'),
            'تبويب الدولار بيعرض الـ ٤٠٠ دولار زي ما هي و بيستبعد المصري');
    }

    public function test_the_main_currency_tab_converts_and_adds_everything(): void
    {
        $this->lg(['lg_code' => 'LG-E', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000, 'lg_currency' => 'EGP']);
        $this->lg(['lg_code' => 'LG-U', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 400, 'lg_currency' => 'USD']);

        DB::table('foreign_exchange_rates')->insert([
            'company_id' => $this->companyId,
            'from_currency' => 'USD',
            'to_currency' => 'EGP',
            'date' => '2026-12-15',
            'exchange_rate' => 50,
        ]);

        // 10,000 + (400 × 50) = 30,000
        $this->assertSame(['12-2026' => 30000.0], $this->companyRefundRow('EGP'));
    }

    public function test_the_company_report_adds_the_refund_to_total_cash_inflow(): void
    {
        $this->lg(['lg_code' => 'LG-COIN', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        $lgData = [];
        $incoming = [];
        $notes = [];

        \App\Services\Reports\CashFlowCompanyPeriodBatchLoader::apply(
            $result,
            \App\Models\ForeignExchangeRate::where('company_id', $this->companyId)->get(),
            'EGP',
            $this->companyId,
            '2026-09-01',
            '2027-06-30',
            ['12-2026' => ['start_date' => '2026-12-01', 'end_date' => '2026-12-31']],
            $lgData,
            'EGP',
            $incoming,
            $notes,
        );

        $inflow = $result['customers'][__('Total Cash Inflow')]['total'] ?? [];
        $this->assertSame(10000.0, round((float) ($inflow['12-2026'] ?? 0), 2));
    }

    /**
     * * أهم اختبار هنا: التقريرين على نفس الشركة لازم يقولوا نفس الرقم
     */
    public function test_the_contract_and_company_reports_agree(): void
    {
        $this->lg(['lg_code' => 'LG-AGREE', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 12345]);

        $contractTotal = array_sum($this->refundRow($this->runContractReport('09/01/2026', '06/30/2027')));
        $companyTotal = array_sum($this->companyRefundRow());

        $this->assertSame(12345.0, round($contractTotal, 2));
        $this->assertSame(round($contractTotal, 2), round($companyTotal, 2));
    }

    /* ──────────── مسار تقرير الـ Consolidated ──────────── */

    /**
     * @return array{cover: float, inflow: float}
     */
    private function supplementTotals(): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        \App\Services\Reports\CashFlowContractPeriodSupplementBatchLoader::apply(
            $result,
            collect([\App\Models\Contract::find($this->contractId)]),
            \App\Models\ForeignExchangeRate::where('company_id', $this->companyId)->get(),
            'EGP',
            $this->companyId,
            '2026-09-01',
            '2027-06-30',
            ['all' => ['start_date' => '2026-09-01', 'end_date' => '2027-06-30']],
        );

        return [
            'cover' => round((float) array_sum($result['customers'][__('Cash Cover')]['total'] ?? []), 2),
            'inflow' => round((float) array_sum($result['customers'][__('Total Cash Inflow')]['total'] ?? []), 2),
        ];
    }

    /**
     * * كان بيقرا عمود debit — مبلغ الإصدار ، فلوس خارجة — و يحطه في
     * * صف فلوس داخلة. خطاب شغال اتحجز كفره و مرجعش منه حاجة كان
     * * بيتعرض كإيراد.
     */
    public function test_the_consolidated_path_never_reports_the_locked_cover_as_income(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-SUPP', 'renewal_date' => '2027-12-31', 'cash_cover_amount' => 41117.70]);

        // حركة الإصدار: الفلوس خرجت
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'type' => 'debit-lg-amount',
            'source' => 'lg-facility',
            'financial_institution_id' => 1,
            'lg_type' => 'final-lgs',
            'date' => '2026-09-10',
            'debit' => 41117.70,
            'credit' => 0,
            'currency' => 'EGP',
        ]);

        // الخطاب بينتهي بعد فترة التقرير ، فمفيش رد متوقّع جوّاها
        $this->assertSame(0.0, $this->supplementTotals()['cover'],
            'مبلغ الإصدار مالوش مكان في صف الفلوس الداخلة');
    }

    /**
     * * كان فيه استعلامين على نفس الداتا — مجمّع و تفصيلي — و الاتنين
     * * بيضيفوا لنفس الصف ، فالرقم كان بيتضاعف
     */
    public function test_the_consolidated_path_counts_each_guarantee_once(): void
    {
        $this->lg(['lg_code' => 'LG-ONCE2', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $this->assertSame(10000.0, $this->supplementTotals()['cover'], 'مش ٢٠٬٠٠٠');
    }

    public function test_the_consolidated_path_counts_several_guarantees_once_each(): void
    {
        $this->lg(['lg_code' => 'LG-M1', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);
        $this->lg(['lg_code' => 'LG-M2', 'renewal_date' => '2027-01-15', 'cash_cover_amount' => 2500]);
        $cancelledId = $this->lg(['lg_code' => 'LG-M3', 'renewal_date' => '2027-05-05', 'cash_cover_amount' => 8000]);
        $this->cancellation($cancelledId, '2026-11-11', 8000);

        $this->assertSame(20500.0, $this->supplementTotals()['cover']);
    }

    public function test_the_consolidated_path_adds_the_refund_to_total_cash_inflow_once(): void
    {
        $this->lg(['lg_code' => 'LG-SUPPIN', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 10000]);

        $this->assertSame(10000.0, $this->supplementTotals()['inflow']);
    }

    public function test_the_consolidated_path_uses_the_remaining_cover_too(): void
    {
        $lgId = $this->lg([
            'lg_code' => 'LG-SUPPADV',
            'lg_type' => 'advanced-payment-lgs',
            'lg_amount' => 100000,
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'renewal_date' => '2026-12-15',
        ]);
        $this->advancePaymentRepaid($lgId, 60000);

        // (100,000 − 60,000) × 10% = 4,000
        $this->assertSame(4000.0, $this->supplementTotals()['cover']);
    }

    /**
     * * التلات مسارات لازم يقولوا نفس الرقم على نفس العقد
     */
    public function test_all_three_reports_agree_on_the_same_contract(): void
    {
        $this->lg(['lg_code' => 'LG-ALL3', 'renewal_date' => '2026-12-15', 'cash_cover_amount' => 12345]);

        $contract = round(array_sum($this->refundRow($this->runContractReport('09/01/2026', '06/30/2027'))), 2);
        $company = round(array_sum($this->companyRefundRow()), 2);
        $consolidated = $this->supplementTotals()['cover'];

        $this->assertSame(12345.0, $contract);
        $this->assertSame(12345.0, $company);
        $this->assertSame(12345.0, $consolidated);
    }

    /* ──────────── الكفر الخارج في تقرير الـ Consolidated ──────────── */

    private function issueCover(int $lgId, float $amount, string $date = '2026-09-10', string $currency = 'EGP'): void
    {
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'type' => 'debit-lg-amount',
            'source' => 'lg-facility',
            'financial_institution_id' => 1,
            'lg_type' => 'final-lgs',
            'date' => $date,
            'debit' => $amount,
            'credit' => 0,
            'currency' => $currency,
        ]);
    }

    /**
     * @return array{issued: float, refunded: float, outflow: float, inflow: float}
     */
    private function supplementBothSides(): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        \App\Services\Reports\CashFlowContractPeriodSupplementBatchLoader::apply(
            $result,
            collect([\App\Models\Contract::find($this->contractId)]),
            \App\Models\ForeignExchangeRate::where('company_id', $this->companyId)->get(),
            'EGP',
            $this->companyId,
            '2026-09-01',
            '2027-06-30',
            ['all' => ['start_date' => '2026-09-01', 'end_date' => '2027-06-30']],
        );

        $lg = __('Letter Of Guarantee');

        return [
            'issued' => round((float) array_sum($result[$lg][__('Issued LG Cash Cover')]['total'] ?? []), 2),
            'refunded' => round((float) array_sum($result['customers'][__('Cash Cover')]['total'] ?? []), 2),
            'outflow' => round((float) array_sum($result['cash_expenses'][$lg]['total'] ?? []), 2),
            'inflow' => round((float) array_sum($result['customers'][__('Total Cash Inflow')]['total'] ?? []), 2),
        ];
    }

    /**
     * * الصف ده ماكانش موجود خالص: الكفر كان بيرجع كإيراد و خروجه
     * * ماكانش بيتعرض ، فـ Net Cash كان متضخّم بمبلغ الكفر بالكامل
     */
    public function test_the_consolidated_path_now_shows_the_locked_cover_as_an_outflow(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-OUT', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 7500);

        $this->assertSame(7500.0, $this->supplementBothSides()['issued']);
    }

    /**
     * * أهم اختبار: اللي خرج لازم يساوي اللي رجع لما الاتنين جوّه المدة
     */
    public function test_the_consolidated_path_balances_what_went_out_with_what_comes_back(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-BAL2', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 7500);

        $t = $this->supplementBothSides();

        $this->assertSame(7500.0, $t['issued']);
        $this->assertSame(7500.0, $t['refunded']);
        $this->assertSame($t['issued'], $t['refunded'], 'الخارج = الراجع');
    }

    public function test_the_locked_cover_reaches_the_outflow_bucket_with_the_fees(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-BUCKET', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 7500);

        $this->assertSame(7500.0, $this->supplementBothSides()['outflow'],
            'نفس دلو المصروفات اللي بيغذّي Total Cash Outflow');
    }

    /**
     * * كفر خطاب الرصيد الافتتاحي اتدفع قبل ما النظام يشتغل ، فمفيش
     * * خروج نعرضه — لكن رجوعه حقيقي فبيفضل في صف الرد
     */
    public function test_an_opening_balance_guarantee_shows_no_outflow_but_still_refunds(): void
    {
        $lgId = $this->lg([
            'lg_code' => 'LG-OPENBAL',
            'category_name' => 'opening-balance',
            'renewal_date' => '2027-01-20',
            'cash_cover_amount' => 9000,
        ]);
        $this->issueCover($lgId, 9000);

        $t = $this->supplementBothSides();

        $this->assertSame(0.0, $t['issued'], 'مفيش خروج نعرضه');
        $this->assertSame(9000.0, $t['refunded'], 'لكن الرجوع حقيقي');
    }

    public function test_an_issuance_outside_the_window_is_not_shown_as_an_outflow(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-OLDISSUE', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 7500, '2024-05-01');

        $this->assertSame(0.0, $this->supplementBothSides()['issued']);
    }

    public function test_the_issuance_is_counted_once_per_movement(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-TWICE', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 5000, '2026-09-10');
        $this->issueCover($lgId, 2500, '2026-10-10');

        $this->assertSame(7500.0, $this->supplementBothSides()['issued'], 'مجموع الحركتين ، من غير تكرار');
    }

    /**
     * * نفس الرقم من تقرير العقد و من مسار الـ Consolidated
     */
    public function test_the_contract_and_consolidated_reports_agree_on_the_outflow(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-AGREE2', 'renewal_date' => '2027-01-20', 'cash_cover_amount' => 7500]);
        $this->issueCover($lgId, 7500);

        $report = $this->runContractReport('09/01/2026', '06/30/2027');
        $contractIssued = round((float) array_sum($report['result']['cash_expenses'][__('Issued LG Cash Cover')]['total'] ?? []), 2);

        $this->assertSame(7500.0, $contractIssued);
        $this->assertSame($contractIssued, $this->supplementBothSides()['issued']);
    }
}

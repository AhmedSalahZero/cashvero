<?php

namespace Tests\Feature\CashFlow;

use App\Enums\LgTypes;
use App\Models\Contract;
use App\Models\LetterOfGuaranteeIssuance;
use App\Services\Reports\CashFlowCompanyPeriodBatchLoader;
use App\Services\Reports\CashFlowContractDetailPeriodBatchLoader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * بوب اب التفاصيل (ℹ️) لصفوف خطابات الضمان في تقرير الكاش فلو
 *
 * * الايقونة كانت على "Cancelled LGs Cash Cover" بس. "Issued LG Cash
 * * Cover" كانت بياناته متجمّعة في letterOfGuaranteeModelData فعلا و
 * * البليد بس مش بيعرضها ، اما "LGs Commission & Fees" فكان التجميع
 * * بتاعه في الـ SQL بيدفن الخطابات جوه نوعها (group by lg_type) فما
 * * كانش فيه اصلا اسم ولا كود يتعرض.
 *
 * * التست بيتأكد من حاجتين مع بعض : ان التفاصيل بقت موجودة ، و ان
 * * اجمالي الصف ما اتغيرش بسبب تغيير الـ group by — التفاصيل ما تستاهلش
 * * إن ارقام التقرير تتحرك.
 */
class LgBreakdownPopupTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    private int $partnerId = 0;

    private int $contractId = 0;

    private int $bankAccountId = 0;

    private const PERIOD_START = '2026-09-01';

    private const PERIOD_END = '2026-10-31';

    /** @var array<string, array{start_date: string, end_date: string}> */
    private const PERIODS = [
        '09-2026' => ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'],
        '10-2026' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
    ];

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
            'name' => json_encode(['en' => 'LG Breakdown Co', 'ar' => 'LG Breakdown Co']),
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
            'status' => Contract::RUNNING,
            'model_type' => 'Customer',
            'name' => 'Guarantee Project',
            'code' => 'c-lgb-'.$this->companyId,
            'start_date' => self::PERIOD_START,
            'end_date' => '2027-06-30',
            'amount' => 100000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);

        $this->bankAccountId = (int) DB::table('financial_institution_accounts')->insertGetId([
            'company_id' => $this->companyId,
            'account_number' => 'acc-lgb-'.$this->companyId,
            'currency' => 'EGP',
            'is_active' => 1,
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
            'lg_type' => LgTypes::FINAL_LGS,
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'issuance_date' => '2026-09-10',
            'renewal_date' => '2027-12-15',
            'lg_amount' => 100000,
            'lg_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
        ], $attributes));
    }

    private function fee(int $lgId, string $date, float $amount, string $flag = 'is_commission_fees'): void
    {
        DB::table('current_account_bank_statements')->insert([
            'company_id' => $this->companyId,
            'financial_institution_account_id' => $this->bankAccountId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'money_received_id' => 0,
            'money_payment_id' => 0,
            'date' => $date,
            'debit' => 0,
            'credit' => $amount,
            $flag => 1,
        ]);
    }

    private function issuedCover(int $lgId, string $date, float $amount): void
    {
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $this->companyId,
            'letter_of_guarantee_issuance_id' => $lgId,
            'type' => 'debit-lg-amount',
            'source' => 'lg-facility',
            'financial_institution_id' => 1,
            'lg_type' => LgTypes::FINAL_LGS,
            'date' => $date,
            'debit' => $amount,
            'credit' => 0,
            'currency' => 'EGP',
        ]);
    }

    /* ───────────────────────── runners ───────────────────────── */

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function runCompanyReport(): array
    {
        $result = [
            'customers' => [__('Total Cash Inflow') => []],
            'suppliers' => [],
            'cash_expenses' => [],
        ];
        $letterOfGuaranteeModelData = [];
        $incomingTransferModelData = [];
        $crossCurrencyNotes = [];

        CashFlowCompanyPeriodBatchLoader::apply(
            $result,
            collect([]),
            'EGP',
            $this->companyId,
            self::PERIOD_START,
            self::PERIOD_END,
            self::PERIODS,
            $letterOfGuaranteeModelData,
            'EGP',
            $incomingTransferModelData,
            $crossCurrencyNotes,
        );

        return [$result, $letterOfGuaranteeModelData];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function runContractReport(): array
    {
        $result = [
            'customers' => [__('Total Cash Inflow') => []],
            'suppliers' => [],
            'cash_expenses' => [],
        ];
        $letterOfGuaranteeModelData = [];

        CashFlowContractDetailPeriodBatchLoader::apply(
            $result,
            $letterOfGuaranteeModelData,
            collect([]),
            'EGP',
            $this->companyId,
            'c-lgb-'.$this->companyId,
            $this->contractId,
            $this->partnerId,
            self::PERIOD_START,
            self::PERIOD_END,
            self::PERIODS,
        );

        return [$result, $letterOfGuaranteeModelData];
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(array $modelData, string $rowName, string $weekKey = '09-2026'): array
    {
        return $modelData[$rowName][LgTypes::getAll()[LgTypes::FINAL_LGS]]['weeks'][$weekKey] ?? [];
    }

    /* ──────────── LGs Commission & Fees ──────────── */

    public function test_the_commission_and_fees_row_carries_a_breakdown(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-FEE']);
        $this->fee($lgId, '2026-09-12', 1500);

        [, $modelData] = $this->runCompanyReport();
        $rows = $this->breakdown($modelData, __('LGs Commission & Fees'));

        $this->assertCount(1, $rows, 'الصف كان بيرجع فاضي: التجميع في الـ SQL كان بيدفن الخطاب جوه نوعه');
        $this->assertSame('LG-FEE', $rows[0]['lg_code']);
        $this->assertSame('Guarantee Customer', $rows[0]['name']);
        $this->assertSame(1500.0, round((float) $rows[0]['amount'], 2));
    }

    public function test_every_kind_of_fee_shows_up_in_the_breakdown(): void
    {
        $renewal = $this->lg(['lg_code' => 'LG-RENEWAL']);
        $issuance = $this->lg(['lg_code' => 'LG-ISSUANCE']);
        $this->fee($renewal, '2026-09-12', 300, 'is_renewal_fees');
        $this->fee($issuance, '2026-09-12', 700, 'is_issuance_fees');

        [, $modelData] = $this->runCompanyReport();
        $codes = array_column($this->breakdown($modelData, __('LGs Commission & Fees')), 'lg_code');
        sort($codes);

        $this->assertSame(['LG-ISSUANCE', 'LG-RENEWAL'], $codes);
    }

    public function test_each_guarantee_keeps_its_own_fee_line(): void
    {
        $first = $this->lg(['lg_code' => 'LG-ONE']);
        $second = $this->lg(['lg_code' => 'LG-TWO']);
        $this->fee($first, '2026-09-12', 1000);
        $this->fee($second, '2026-09-12', 2000);

        [, $modelData] = $this->runCompanyReport();
        $rows = $this->breakdown($modelData, __('LGs Commission & Fees'));

        $byCode = array_combine(array_column($rows, 'lg_code'), array_map(fn ($row) => round((float) $row['amount'], 2), $rows));
        ksort($byCode);

        $this->assertSame(['LG-ONE' => 1000.0, 'LG-TWO' => 2000.0], $byCode,
            'نفس النوع و نفس اليوم — و برضه لازم يفضلوا سطرين مختلفين في البوب اب');
    }

    public function test_the_fee_row_total_is_unchanged_by_the_finer_grouping(): void
    {
        $first = $this->lg(['lg_code' => 'LG-SUM-A']);
        $second = $this->lg(['lg_code' => 'LG-SUM-B']);
        $this->fee($first, '2026-09-12', 1000);
        $this->fee($first, '2026-09-20', 250.5);
        $this->fee($second, '2026-09-12', 2000);
        $this->fee($second, '2026-10-05', 40);

        [$result, $modelData] = $this->runCompanyReport();
        $feesRow = $result['cash_expenses'][__('LGs Commission & Fees')];

        $this->assertSame(3250.5, round((float) $feesRow['total']['09-2026'], 2), 'اجمالي سبتمبر لازم يفضل زي ما هو');
        $this->assertSame(40.0, round((float) $feesRow['total']['10-2026'], 2));

        $septemberBreakdown = array_sum(array_column($this->breakdown($modelData, __('LGs Commission & Fees')), 'amount'));
        $this->assertSame(3250.5, round((float) $septemberBreakdown, 2), 'مجموع البوب اب لازم يساوي الخلية بالظبط');
    }

    public function test_two_fee_movements_on_one_day_are_one_line_for_that_guarantee(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-SAMEDAY']);
        $this->fee($lgId, '2026-09-12', 600);
        $this->fee($lgId, '2026-09-12', 400);

        [, $modelData] = $this->runCompanyReport();
        $rows = $this->breakdown($modelData, __('LGs Commission & Fees'));

        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, round((float) $rows[0]['amount'], 2));
    }

    /**
     * * الاسم بيتقرا بـ leftJoin ، فالخطاب اللي مالوش partner لازم يفضل
     * * بمبلغه في التقرير — اسم ناقص ما يصحش يبلع فلوس
     */
    public function test_a_guarantee_without_a_partner_still_counts(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-NOPARTNER', 'partner_id' => null]);
        $this->fee($lgId, '2026-09-12', 800);

        [$result, $modelData] = $this->runCompanyReport();
        $rows = $this->breakdown($modelData, __('LGs Commission & Fees'));

        $this->assertSame(800.0, round((float) $result['cash_expenses'][__('LGs Commission & Fees')]['total']['09-2026'], 2));
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['name']);
    }

    public function test_fees_of_another_company_never_leak_in(): void
    {
        $mine = $this->lg(['lg_code' => 'LG-MINE']);
        $this->fee($mine, '2026-09-12', 100);

        $otherCompanyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Other Co', 'ar' => 'Other Co']),
            'main_functional_currency' => 'EGP',
        ]);
        $theirs = $this->lg(['lg_code' => 'LG-THEIRS', 'company_id' => $otherCompanyId, 'contract_id' => null]);
        DB::table('current_account_bank_statements')->insert([
            'company_id' => $otherCompanyId,
            'financial_institution_account_id' => $this->bankAccountId,
            'letter_of_guarantee_issuance_id' => $theirs,
            'money_received_id' => 0,
            'money_payment_id' => 0,
            'date' => '2026-09-12',
            'debit' => 0,
            'credit' => 999,
            'is_commission_fees' => 1,
        ]);

        [, $modelData] = $this->runCompanyReport();

        $this->assertSame(['LG-MINE'], array_column($this->breakdown($modelData, __('LGs Commission & Fees')), 'lg_code'));
    }

    /* ──────────── Issued LG Cash Cover ──────────── */

    public function test_the_issued_cash_cover_row_carries_a_breakdown(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-ISSUED']);
        $this->issuedCover($lgId, '2026-09-15', 10000);

        [, $modelData] = $this->runCompanyReport();
        $rows = $this->breakdown($modelData, __('Issued LG Cash Cover'));

        $this->assertCount(1, $rows);
        $this->assertSame('LG-ISSUED', $rows[0]['lg_code']);
        $this->assertSame('Guarantee Customer', $rows[0]['name']);
        $this->assertSame(10000.0, round((float) $rows[0]['amount'], 2));
    }

    /* ──────────── تقرير العقد بياخد نفس المعاملة ──────────── */

    public function test_the_contract_report_carries_the_fee_breakdown_too(): void
    {
        $lgId = $this->lg(['lg_code' => 'LG-CONTRACT-FEE']);
        $this->fee($lgId, '2026-09-12', 1750);

        [$result, $modelData] = $this->runContractReport();
        $rows = $this->breakdown($modelData, __('LGs Commission & Fees'));

        $this->assertCount(1, $rows);
        $this->assertSame('LG-CONTRACT-FEE', $rows[0]['lg_code']);
        $this->assertSame('Guarantee Customer', $rows[0]['name']);
        $this->assertSame(1750.0, round((float) $result['cash_expenses'][__('LGs Commission & Fees')]['total']['09-2026'], 2));
    }
}

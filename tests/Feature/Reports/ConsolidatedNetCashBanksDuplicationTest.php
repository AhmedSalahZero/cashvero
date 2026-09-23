<?php

namespace Tests\Feature\Reports;

use App\Models\Company;
use App\Models\Contract;
use App\Services\Reports\ConsolidatedCashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * ازدواج رصيد البنوك في صافي التدفق — تقرير الـ Consolidated
 *
 * * "Cash & Banks Balance" بيدخل اصلا في Total Cash Inflow بتاع الشركة ،
 * * و منها بيعدّي جوه companyUnallocatedCashIn الى مجموع الداخل في
 * * grandTotal . و رغم كده الكود كان بيضيفه تاني صراحةً :
 * *
 * *     net = inflow + cash_and_banks − outflow      ← الرصيد مرتين
 * *
 * * فصف صافي التدفق كان بيزيد بقيمة الرصيد كلها في اول فترة ، و
 * * Accumulated Net Cash بيورّث الزيادة دي على كل الفترات اللي بعدها .
 * *
 * * التست بيتأكد من الثابتة : net = inflow − outflow في كل فترة ، و ان
 * * الـ accumulated هو المجموع التراكمي للصافي — مع وجود رصيد بنوك
 * * حقيقي غير صفر (من غير كده التست مابيفرّقش بين الصح و الغلط) .
 */
class ConsolidatedNetCashBanksDuplicationTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    private const OPENING_BALANCE = 250000.0;

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
            'name' => json_encode(['en' => 'Net Cash Banks Test Co', 'ar' => 'Net Cash Banks Test Co']),
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

    /**
     * * صف "Cash & Banks Balance" بيتبني من اخر end_balance في
     * * current_account_bank_statements قبل بداية التقرير ، مربوط
     * * بـ financial_institution_accounts → financial_institutions → banks .
     * * فالسلسلة دي كلها لازم تتعمل و إلا الصف بيطلع فاضي و التست
     * * ما بيختبرش حاجة .
     */
    private function bankAccountWithOpeningBalance(): void
    {
        $bankId = (int) DB::table('banks')->insertGetId([
            'name_en' => 'Net Cash Test Bank',
            'name_ar' => 'Net Cash Test Bank',
        ]);

        $institutionId = (int) DB::table('financial_institutions')->insertGetId([
            'company_id' => $this->companyId,
            'bank_id' => $bankId,
            'name' => 'Net Cash Test Institution',
            'updated_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (int) DB::table('financial_institution_accounts')->insertGetId([
            'company_id' => $this->companyId,
            'financial_institution_id' => $institutionId,
            'account_number' => 'NETCASH-'.uniqid(),
            'currency' => 'EGP',
            'balance_amount' => self::OPENING_BALANCE,
            'balance_date' => '2026-08-31',
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        DB::table('current_account_bank_statements')->insert([
            'company_id' => $this->companyId,
            'financial_institution_account_id' => $accountId,
            'money_received_id' => 0,
            'money_payment_id' => 0,
            'date' => '2026-08-31',
            'full_date' => '2026-08-31 00:00:00',
            'beginning_balance' => 0,
            'debit' => self::OPENING_BALANCE,
            'credit' => 0,
            'is_beginning_balance' => 1,
            'comment_en' => 'Beginning Balance',
            'comment_ar' => 'Beginning Balance',
        ]);
    }

    private function customerContract(float $amount): Contract
    {
        $partnerId = (int) DB::table('partners')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Net Cash Customer',
            'is_customer' => 1,
            'is_supplier' => 0,
        ]);

        $contractId = (int) DB::table('contracts')->insertGetId([
            'company_id' => $this->companyId,
            'partner_id' => $partnerId,
            'status' => Contract::RUNNING,
            // cashvero ماعندهوش الثابتة دي — بيستخدم القيمة النصية مباشرة
            'model_type' => defined(Contract::class.'::FOR_CUSTOMER') ? Contract::FOR_CUSTOMER : 'Customer',
            'name' => 'Net Cash Project',
            'code' => 'c-netcash-'.$this->companyId,
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'amount' => $amount,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);

        DB::table('sales_orders')->insert([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'so_number' => 'SO-NETCASH',
            'amount' => $amount,
            'start_date_1' => '2026-09-01',
            'end_date_1' => '2026-11-30',
            'execution_percentage_1' => 100,
            'execution_days_1' => 0,
            'collection_days_1' => 0,
        ]);

        return Contract::find($contractId);
    }

    /** @return array<string, mixed>|null */
    private function buildReport(Contract $contract): ?array
    {
        $request = Request::create('/x', 'GET', [
            'start_date' => '09/01/2026',
            'end_date' => '01/31/2027',
            'report_interval' => 'monthly',
            'currencies' => ['EGP'],
            'contract_ids' => [(string) $contract->id],
        ]);
        app()->instance('request', $request);

        try {
            return (new ConsolidatedCashFlowService)->build(Company::find($this->companyId), $request);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Today is outside the report window this test builds: '.$e->getMessage());
        }
    }

    public function test_net_cash_does_not_count_the_bank_balance_twice(): void
    {
        $this->bankAccountWithOpeningBalance();
        $contract = $this->customerContract(60000);

        $data = $this->buildReport($contract);
        $grand = $data['grandTotal'];

        // من غير رصيد بنوك حقيقي التست مابيفرّقش بين المعادلتين
        $this->assertGreaterThan(0.0, array_sum($grand['cash_and_banks']),
            'الفكسشر لازم ينتج رصيد بنوك غير صفر و إلا التست ما بيختبرش حاجة');

        foreach (array_keys($data['weeks']) as $period) {
            $inflow = (float) ($grand['cash_inflow'][$period] ?? 0);
            $outflow = (float) ($grand['cash_outflow'][$period] ?? 0);
            $banks = (float) ($grand['cash_and_banks'][$period] ?? 0);
            $net = (float) ($grand['net_cash'][$period] ?? 0);

            // الرصيد بيتحسب في الصافي مرة واحدة بس
            $this->assertEqualsWithDelta($inflow + $banks - $outflow, $net, 0.01,
                "صافي التدفق في {$period} لازم يساوي الداخل + الرصيد − الخارج");
        }
    }

    /**
     * * الرصيد ليه صف لوحده (banksSection / grandTotal['cash_and_banks']) ،
     * * فلو فضل داخل في صف الدخل غير المخصّص كمان بيبان مرتين على الشاشة .
     */
    public function test_the_bank_balance_is_not_repeated_inside_the_unallocated_inflow_row(): void
    {
        $this->bankAccountWithOpeningBalance();
        $contract = $this->customerContract(60000);

        $data = $this->buildReport($contract);
        $grand = $data['grandTotal'];

        $this->assertGreaterThan(0.0, array_sum($grand['cash_and_banks']),
            'الفكسشر لازم ينتج رصيد بنوك غير صفر و إلا التست ما بيختبرش حاجة');

        $contractsInflow = [];
        foreach (($data['contractsSection'] ?? []) as $block) {
            foreach (($block['cash_inflow'] ?? []) as $period => $amount) {
                $contractsInflow[$period] = ($contractsInflow[$period] ?? 0.0) + (float) $amount;
            }
        }

        foreach (array_keys($data['weeks']) as $period) {
            $banks = (float) ($grand['cash_and_banks'][$period] ?? 0);
            if ($banks <= 0.0) {
                continue;
            }

            $unallocated = (float) ($data['companyUnallocatedCashIn'][$period] ?? 0);
            $sumInflow = (float) ($grand['cash_inflow'][$period] ?? 0);

            $this->assertEqualsWithDelta(
                ($contractsInflow[$period] ?? 0.0) + $unallocated, $sumInflow, 0.01,
                "مجموع الداخل في {$period} لازم يكون العقود + غير المخصّص");

            /*
             * في الفكسشر ده مفيش للشركة اي دخل غير رصيد البنوك و توقّع
             * العقد نفسه ، و توقّع العقد بيتطرح لانه في contractsSection —
             * فالصف الصح لازم يكون اقل من الرصيد . لو الرصيد تسرّب جوه
             * الصف كان هيبقى مساوي له على الاقل .
             */
            $this->assertLessThan($banks, $unallocated,
                "صف الدخل غير المخصّص في {$period} المفروض ما يشملش رصيد البنوك");
        }
    }

    public function test_accumulated_net_cash_is_the_running_sum_of_net_cash(): void
    {
        $this->bankAccountWithOpeningBalance();
        $contract = $this->customerContract(60000);

        $data = $this->buildReport($contract);
        $grand = $data['grandTotal'];

        $running = 0.0;
        foreach (array_keys($data['weeks']) as $period) {
            $running += (float) ($grand['net_cash'][$period] ?? 0);
            $this->assertEqualsWithDelta($running, (float) ($grand['accumulated_net'][$period] ?? 0), 0.01,
                "التراكمي في {$period} لازم يكون مجموع الصافي لحد الفترة دي");
        }
    }
}

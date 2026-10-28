<?php

namespace Tests\Feature\MediumTermLoan;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LoanSchedule;
use App\Models\LoanScheduleSettlement;
use App\Models\MediumTermLoan;
use App\Models\MediumTermLoanEarlySettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * السداد المعجل للقرض متوسط الاجل (Early Settlement).
 *
 * السيناريو: قرض 6 اقساط ، كل قسط 100 الف = 20 الف فايدة + 80 الف اصل.
 * الاول والتاني اتدفعوا بالكامل ، والتالت اتدفع منه 50 الف بس (20 فايدة +
 * 30 اصل لان الفايدة بتتسدد الاول). فا السداد المعجل بيقفل من نص التالت
 * لحد السادس: الاصل المتبقي = 50 + 3×80 = 290 الف ، والفايدة اللي مش
 * هتتدفع = 3×20 = 60 الف.
 *
 * التستات دي بتشتغل على قاعدة بيانات التطوير زي باقي السويت (مافيش سكيما تست
 * متهاجرة هنا) ، جوه ترانزاكشن بيترجع فيها كل صف اتعمل ، وبتتسكب لوحدها لو
 * القاعدة مش شغالة.
 */
class EarlySettlementTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    private FinancialInstitutionAccount $account;

    private AccountType $currentAccountType;

    private User $actor;

    private MediumTermLoan $loan;

    /** @var list<LoanSchedule> */
    private array $schedules = [];

    private string $currency = 'EGP';

    /** @see \Tests\Feature\Deposits\DepositSettlementAccountTest::createApplication() */
    public function createApplication()
    {
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = 'en';
        $_SERVER['ROUTING_LOCALE'] = 'en';

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('ROUTING_LOCALE');
        unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);

        parent::tearDown();
    }

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
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

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database has no financial institution to attach accounts to.');
        }
        $this->bank = $bank;
        $this->company = Company::findOrFail($bank->company_id);
        $this->currentAccountType = AccountType::onlyCurrentAccount()->firstOrFail();

        $this->account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-MTL-ES-'.uniqid(),
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subYear()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);
        DB::table('current_account_bank_statements')->insert([
            'financial_institution_account_id' => $this->account->id,
            'company_id' => $this->company->id,
            'date' => now()->subMonths(6)->format('Y-m-d'),
            'full_date' => now()->subMonths(6)->format('Y-m-d H:i:s'),
            'is_beginning_balance' => 1,
            'beginning_balance' => 0,
            'debit' => 1000000,
            'end_balance' => 1000000,
        ]);

        $this->loan = MediumTermLoan::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'name' => 'Early Settlement Test '.uniqid(),
            'start_date' => now()->subMonths(4)->format('Y-m-d'),
            'end_date' => now()->addMonths(4)->format('Y-m-d'),
            'currency' => $this->currency,
            'limit' => 480000,
            'account_number' => 'MTL-ES-'.uniqid(),
            'duration' => 6,
            'installment_payment_interval' => 'monthly',
        ]);

        foreach ([-3, -2, -1, 1, 2, 3] as $monthOffset) {
            $this->schedules[] = LoanSchedule::create([
                'company_id' => $this->company->id,
                'medium_term_loan_id' => $this->loan->id,
                'date' => now()->addMonths($monthOffset)->format('Y-m-d'),
                'schedule_payment' => 100000,
                'interest_amount' => 20000,
                'principle_amount' => 80000,
                'remaining' => 100000,
            ]);
        }
        $this->paySettlement($this->schedules[0], 100000, now()->subMonths(3));
        $this->paySettlement($this->schedules[1], 100000, now()->subMonths(2));
        $this->paySettlement($this->schedules[2], 50000, now()->subMonth());

        $this->actor = User::create([
            'name' => 'MTL Early Settlement Test '.uniqid(),
            'email' => 'mtl-early-settlement-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $this->actor->givePermissionTo(['medium_term_loan.view', 'medium_term_loan.manage_schedule']);
        $this->actor->companies()->attach($this->company->id);
        $this->actor->load('companies');
        $this->assertFalse($this->actor->isSuperAdmin(), 'اليوزر لازم مايتخطاش فحص الصلاحيات.');
    }

    /**
     * * سداد عادي من غير حركة كشف — التستات هنا مش بتختبر السداد العادي ،
     * * محتاجين بس الاقساط تبقى مدفوعة كلها او جزء منها
     */
    private function paySettlement(LoanSchedule $schedule, float $amount, $date): void
    {
        $schedule->settlements()->create([
            'company_id' => $this->company->id,
            'current_account_number' => $this->account->account_number,
            'amount' => $amount,
            'date' => $date->format('Y-m-d'),
        ]);
    }

    private function url(string $method): string
    {
        return route('loans.early.settlement.'.$method, [
            'company' => $this->company->id,
            'financialInstitution' => $this->bank->id,
            'mediumTermLoan' => $this->loan->id,
        ]);
    }

    private function settleEarly(array $overrides = [])
    {
        return $this->actingAs($this->actor)->post($this->url('store'), array_merge([
            'date' => now()->format('Y-m-d'),
            'amount' => 290000,
            'fee_amount' => 5000,
            'account_type_id' => $this->currentAccountType->id,
            'account_number' => $this->account->account_number,
        ], $overrides));
    }

    private function linkedCreditRows(MediumTermLoanEarlySettlement $earlySettlement)
    {
        return DB::table('current_account_bank_statements')
            ->where('medium_term_loan_early_settlement_id', $earlySettlement->id)
            ->orderBy('credit', 'desc')
            ->pluck('credit')
            ->map(fn ($credit) => (float) $credit)
            ->all();
    }

    public function test_the_preview_starts_from_the_partially_paid_installment(): void
    {
        $preview = $this->loan->getEarlySettlementPreview();

        $this->assertSame($this->schedules[2]->id, $preview['first_loan_schedule_id']);
        $this->assertSame(3, $preview['first_installment_number']);
        $this->assertSame(4, $preview['open_installments_count']);
        // التالت: اللي اتدفع (50) راح للفايدة (20) الاول وبعدين 30 من الاصل
        $this->assertEquals(50000 + 3 * 80000, $preview['remaining_principle']);
        $this->assertEquals(3 * 20000, $preview['remaining_interest']);
    }

    public function test_early_settlement_closes_every_open_installment_and_posts_amount_and_fee(): void
    {
        $response = $this->settleEarly();

        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $loan = $this->loan->fresh();
        $this->assertSame(MediumTermLoan::EARLY_SETTLED, $loan->status);

        $earlySettlement = $loan->earlySettlement;
        $this->assertNotNull($earlySettlement);
        $this->assertEquals(290000, $earlySettlement->principle_settled);
        $this->assertEquals(60000, $earlySettlement->interest_waived);

        // اللي اتدفع قبل السداد المعجل بيفضل زي ما هو
        $this->assertSame('paid', $this->schedules[0]->fresh()->status);
        $this->assertSame('paid', $this->schedules[1]->fresh()->status);
        $this->assertSame(3, LoanScheduleSettlement::whereIn('loan_schedule_id', collect($this->schedules)->pluck('id'))->count());

        foreach (array_slice($this->schedules, 2) as $schedule) {
            $schedule = $schedule->fresh();
            $this->assertSame('early_settled', $schedule->status);
            $this->assertEquals(0, $schedule->remaining);
            $this->assertSame($earlySettlement->id, (int) $schedule->medium_term_loan_early_settlement_id);
        }

        // حركة فلوس واحدة بس: صف بالقيمة وصف بالغرامة
        $this->assertSame([290000.0, 5000.0], $this->linkedCreditRows($earlySettlement));

        // التدفق النقدي والداشبورد: مفيش اقساط جاية على القرض دا
        $this->assertSame(0, LoanSchedule::where('medium_term_loan_id', $this->loan->id)->where('remaining', '>', 0)->count());
        $this->assertNull($loan->getNextInstallmentDateAndAmount(now()->format('Y-m-d'))['date_formatted']);
        $this->assertSame([], $loan->getLoanPastDuesDetailsArray());
    }

    public function test_installment_payment_is_blocked_after_early_settlement(): void
    {
        $this->settleEarly()->assertSessionMissing('fail');

        $response = $this->actingAs($this->actor)->post(route('store.loan.schedule.settlements', [
            'company' => $this->company->id,
            'loanSchedule' => $this->schedules[3]->id,
        ]), [
            'date' => now()->format('Y-m-d'),
            'amount' => 100000,
            'current_account_number' => $this->account->account_number,
        ]);

        $response->assertSessionHas('fail');
        $this->assertSame(0, $this->schedules[3]->settlements()->count());
    }

    public function test_deleting_the_early_settlement_restores_everything(): void
    {
        $this->settleEarly()->assertSessionMissing('fail');
        $earlySettlement = $this->loan->fresh()->earlySettlement;

        $response = $this->actingAs($this->actor)->delete($this->url('destroy'));

        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $this->assertSame(MediumTermLoan::RUNNING, $this->loan->fresh()->status);
        $this->assertNull(MediumTermLoanEarlySettlement::find($earlySettlement->id));
        $this->assertSame([], $this->linkedCreditRows($earlySettlement));

        $third = $this->schedules[2]->fresh();
        $this->assertEquals(50000, $third->remaining);
        $this->assertSame('partially_paid_and_past_due', $third->status);
        $this->assertNull($third->medium_term_loan_early_settlement_id);

        foreach (array_slice($this->schedules, 3) as $schedule) {
            $schedule = $schedule->fresh();
            $this->assertEquals(100000, $schedule->remaining);
            $this->assertSame('not_due_yet', $schedule->status);
        }
    }

    public function test_editing_the_early_settlement_replaces_its_movements(): void
    {
        $this->settleEarly()->assertSessionMissing('fail');

        $response = $this->actingAs($this->actor)->patch($this->url('update'), [
            'date' => now()->format('Y-m-d'),
            'amount' => 280000,
            'fee_amount' => 0,
            'account_type_id' => $this->currentAccountType->id,
            'account_number' => $this->account->account_number,
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $loan = $this->loan->fresh();
        $this->assertSame(MediumTermLoan::EARLY_SETTLED, $loan->status);
        $this->assertSame(1, MediumTermLoanEarlySettlement::where('medium_term_loan_id', $loan->id)->count());
        $this->assertSame([280000.0], $this->linkedCreditRows($loan->earlySettlement));
        foreach (array_slice($this->schedules, 2) as $schedule) {
            $this->assertSame('early_settled', $schedule->fresh()->status);
        }
    }

    public function test_insufficient_balance_changes_nothing(): void
    {
        $response = $this->settleEarly(['amount' => 2000000]);

        $response->assertSessionHas('fail');
        $this->assertSame(MediumTermLoan::RUNNING, $this->loan->fresh()->status);
        $this->assertNull($this->loan->fresh()->earlySettlement);
        $this->assertSame('not_due_yet', $this->schedules[5]->fresh()->status);
    }

    public function test_an_account_at_another_bank_or_currency_is_rejected(): void
    {
        $usdAccount = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-MTL-ES-USD-'.uniqid(),
            'currency' => 'USD',
            'balance_amount' => 0,
            'balance_date' => now()->subYear()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        $response = $this->settleEarly(['account_number' => $usdAccount->account_number]);

        $response->assertSessionHas('fail');
        $this->assertSame(MediumTermLoan::RUNNING, $this->loan->fresh()->status);
    }
}

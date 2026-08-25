<?php

namespace Tests\Feature\Deposits;

use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\TimeOfDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * حساب التسوية في بوب اب "Apply Deposit" و "Break" للوديعة الزمنية (TD)
 * وشهادة الايداع (CD).
 *
 * الخلفية: الوديعة اللي اتسجلت من غير ما اليوزر يختار
 * Deducted From Account
 * بتتعتبر
 * opening balance
 * وبالتالي مالهاش
 * deducted_from_account_id
 * — فا وقت ما اليوزر ييجي يحدد الوديعة على انها
 * Matured
 * او يكسرها ماكناش بنلاقي حساب نرد عليه اصل الوديعة (والقيد بتاع اودو كان
 * بيقع على
 * FinancialInstitutionAccount::find(null)->getAccountNumber()
 * ). دلوقتي البوب اب نفسه بيسأل عن الحساب ، وقيمته الافتراضية هي حساب الخصم
 * الاصلي لو كان موجود.
 *
 * التستات دي بتشتغل على قاعدة بيانات التطوير زي باقي السويت (مافيش سكيما تست
 * متهاجرة هنا) ، جوه ترانزاكشن بيترجع فيها كل صف اتعمل ، وبتتسكب لوحدها لو
 * القاعدة مش شغالة.
 */
class DepositSettlementAccountTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    /** بنك تاني علشان نتأكد ان حساباته مابتتقبلش كحساب تسوية */
    private ?FinancialInstitution $otherBank = null;

    private FinancialInstitutionAccount $fundingAccount;

    private FinancialInstitutionAccount $alternativeAccount;

    private FinancialInstitutionAccount $maturityAccount;

    private FinancialInstitutionAccount $otherCurrencyAccount;

    private FinancialInstitutionAccount $closedAccount;

    private User $actor;

    private string $currency = 'EGP';

    private string $otherCurrency = 'USD';

    /**
     * DatabaseTransactions بيفتح الترانزاكشن من setUpTraits اللي بتشتغل قبل
     * جسم setUp ، فا لازم نوجّه الكونكشن هنا وإلا الترانزاكشن هتتفتح على
     * السكيما بتاعة التست (اللي مش موجودة) قبل ما حد ياخد باله.
     */
    /**
     * الراوتس كلها متسجلة تحت
     * Route::group(['prefix' => LaravelLocalization::setLocale()])
     * ، وتحت
     * php artisan serve
     * البريفكس ده بيبقى /{lang} فا لينك زي
     * /en/{company}/financial-institutions/...
     * بيخلي
     * getCurrentCompanyId()
     * (اللي بيقرا Request()->segment(2)) يرجع رقم الشركة صح.
     *
     * في بروسيس التست مافيش بريفكس ، فا رقم الشركة كان بيقع في السيجمنت
     * الاولانية والكود بتاع البانك استيتمنت كان بياخد
     * 'financial-institutions'
     * على انه company id. ROUTING_LOCALE بيخلي الباكدج تسجل البريفكس زي
     * الانتاج بالظبط ، فا الريكوستات هنا بتعدي على نفس اللينكات الحقيقية.
     */
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

        /*
         * من غير كده كل ريكوست بيعمل 302 على البريفكس بتاع اللغة قبل ما يوصل
         * للكونترولر — نفس الحتة اللي PaginationSmokeTest شارحها. الاوث
         * والصلاحيات سايبينهم شغالين.
         */
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
        $this->otherBank = FinancialInstitution::query()
            ->where('company_id', $this->company->id)
            ->where('id', '!=', $this->bank->id)
            ->first();

        $this->fundingAccount = $this->makeAccount('TEST-FUND-'.uniqid());
        $this->alternativeAccount = $this->makeAccount('TEST-ALT-'.uniqid());
        $this->maturityAccount = $this->makeAccount('TEST-MAT-'.uniqid());
        $this->otherCurrencyAccount = $this->makeAccount('TEST-USD-'.uniqid(), $this->otherCurrency);
        $this->closedAccount = $this->makeAccount('TEST-CLOSED-'.uniqid(), $this->currency, false);

        $this->actor = $this->makeUser();
    }

    private function makeAccount(string $accountNumber, ?string $currency = null, bool $isActive = true): FinancialInstitutionAccount
    {
        return FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => $accountNumber,
            'currency' => $currency ?: $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subYears(3)->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = User::create([
            'name' => 'Deposit Settlement Test '.uniqid(),
            'email' => 'deposit-settlement-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);

        /*
         * EnforcePermission بيقف قدام الراوتس دي بصلاحية الـ settle ، فا
         * بندي اليوزر الصلاحيات اللي التستات محتاجاها بالظبط — من غير ما
         * يبقى super admin علشان الجيتات تشتغل فعلا.
         */
        $user->givePermissionTo([
            'time_of_deposit.view',
            'time_of_deposit.settle',
            'certificate_of_deposit.view',
            'certificate_of_deposit.settle',
        ]);

        $user->companies()->attach($this->company->id);
        $user->load('companies');

        $this->assertFalse($user->isSuperAdmin(), 'اليوزر لازم مايتخطاش فحص الصلاحيات.');

        /*
         * الحساب ده مالوش يوزر/باسورد اودو ، يعني
         * Company::hasOdooIntegrationCredentials()
         * بترجع false وبالتالي مافيش اي كول حقيقي على اودو من التستات دي.
         */
        $this->assertFalse($this->company->hasOdooIntegrationCredentials($user));

        return $user;
    }

    /**
     * @return TimeOfDeposit|CertificatesOfDeposit
     */
    private function makeDeposit(string $kind, ?int $deductedFromAccountId)
    {
        $class = $kind === 'td' ? TimeOfDeposit::class : CertificatesOfDeposit::class;

        return $class::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => strtoupper($kind).'-TEST-'.uniqid(),
            'amount' => 100000,
            'currency' => $this->currency,
            'interest_rate' => 10,
            'interest_amount' => 1000,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->subDay()->format('Y-m-d'),
            'status' => $class::RUNNING,
            'maturity_amount_added_to_account_id' => $this->maturityAccount->id,
            'deducted_from_account_id' => $deductedFromAccountId,
            'is_at_maturity' => 1,
            'is_active' => 1,
        ]);
    }

    private function routeFor(string $kind, string $action, $deposit): string
    {
        $name = $kind === 'td'
            ? str_replace('{kind}', 'time.of.deposit', $action)
            : str_replace('{kind}', 'certificate.of.deposit', $action);

        $parameters = [
            'company' => $this->company->id,
            'financialInstitution' => $this->bank->id,
        ];
        $parameters[$kind === 'td' ? 'timeOfDeposit' : 'certificatesOfDeposit'] = $deposit->id;

        return route($name, $parameters);
    }

    private function applyDepositUrl(string $kind, $deposit): string
    {
        return $this->routeFor($kind, 'apply.deposit.to.{kind}', $deposit);
    }

    private function applyBreakUrl(string $kind, $deposit): string
    {
        return $this->routeFor($kind, 'apply.break.to.{kind}', $deposit);
    }

    private function reverseDepositUrl(string $kind, $deposit): string
    {
        return $this->routeFor($kind, 'reverse.deposit.to.{kind}', $deposit);
    }

    private function reverseBrokenUrl(string $kind, $deposit): string
    {
        return $this->routeFor($kind, 'reverse.broken.to.{kind}', $deposit);
    }

    public static function kinds(): array
    {
        return ['time of deposit' => ['td'], 'certificate of deposit' => ['cd']];
    }

    /* ─────────────── القيمة الافتراضية في البوب اب ─────────────── */

    /** @dataProvider kinds */
    public function test_the_popup_defaults_to_the_deducted_from_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->fundingAccount->id);

        $this->assertSame($this->fundingAccount->id, $deposit->getSettlementOrDeductedFromAccountId());
    }

    /** @dataProvider kinds */
    public function test_an_opening_balance_deposit_has_no_default_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $this->assertTrue($deposit->isOpeningBalance());
        $this->assertNull($deposit->getSettlementOrDeductedFromAccountId());
    }

    /** @dataProvider kinds */
    public function test_a_chosen_settlement_account_wins_over_the_deducted_from_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->fundingAccount->id);
        $deposit->update(['settlement_account_id' => $this->alternativeAccount->id]);

        $this->assertSame($this->alternativeAccount->id, $deposit->fresh()->getSettlementOrDeductedFromAccountId());
    }

    /* ─────────────────── الحسابات المعروضة ─────────────────── */

    /** @dataProvider kinds */
    public function test_only_active_same_bank_same_currency_accounts_are_offered(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->fundingAccount->id);

        $offered = $deposit->getSettlementAccountOptions()->pluck('id')->all();

        dump([
            'guardableColumnsCache' => (function () {
                $r = new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns');
                $r->setAccessible(true);
                $all = $r->getValue();
                return $all[\App\Models\FinancialInstitutionAccount::class] ?? 'NOT CACHED';
            })(),
            'class' => get_class($this->alternativeAccount),
            'unguarded' => \Illuminate\Database\Eloquent\Model::isUnguarded(),
            'fillable' => $this->alternativeAccount->getFillable(),
            'guarded' => $this->alternativeAccount->getGuarded(),
            'attrs' => $this->alternativeAccount->getAttributes(),
            'raw_row' => (array) \Illuminate\Support\Facades\DB::table('financial_institution_accounts')->find($this->alternativeAccount->id),
            'db_name' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
            'deposit_currency' => $deposit->getCurrency(),
            'deposit_fi' => $deposit->financial_institution_id,
            'bank' => $this->bank->id,
            'alt' => [$this->alternativeAccount->id, $this->alternativeAccount->currency, $this->alternativeAccount->is_active, $this->alternativeAccount->financial_institution_id],
            'fund' => [$this->fundingAccount->id, $this->fundingAccount->currency, $this->fundingAccount->is_active],
            'rel_count' => $deposit->financialInstitution->accounts->count(),
            'rel_has_alt' => $deposit->financialInstitution->accounts->contains('id', $this->alternativeAccount->id),
            'offered' => $offered,
        ]);

        $this->assertContains($this->fundingAccount->id, $offered);
        $this->assertContains($this->alternativeAccount->id, $offered);
        $this->assertNotContains($this->otherCurrencyAccount->id, $offered, 'حساب بعملة تانية مالوش لازمة هنا');
        $this->assertNotContains($this->closedAccount->id, $offered, 'الحساب المقفول مايتعرضش');
    }

    /** @dataProvider kinds */
    public function test_a_closed_account_still_shows_when_it_is_the_current_one(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->closedAccount->id);

        $this->assertContains($this->closedAccount->id, $deposit->getSettlementAccountOptions()->pluck('id')->all());
    }

    /* ─────────────────── Apply Deposit ─────────────────── */

    /** @dataProvider kinds */
    public function test_applying_deposit_stores_the_chosen_settlement_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
            'settlement_account_id' => $this->alternativeAccount->id,
        ]);

        /*
         * مابنعملش assert على فلاش الـ success نفسه: باكدج flasher بيسحب
         * المفتاح ده من السيشن وهو بيحوّله لـ envelope ، فا غيابه مايعنيش
         * فشل. اللي بيهمنا ان مافيش fail وان الحالة اتغيرت فعلا.
         */
        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $stored = $deposit->fresh();
        $this->assertSame($this->alternativeAccount->id, $stored->getSettlementAccountId());
        $this->assertSame($stored::MATURED, $stored->getStatus());
    }

    /** @dataProvider kinds */
    public function test_applying_deposit_without_a_settlement_account_changes_nothing(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
        ]);

        $response->assertSessionHas('fail');

        $stored = $deposit->fresh();
        $this->assertNull($stored->getSettlementAccountId());
        $this->assertSame($stored::RUNNING, $stored->getStatus());
        $this->assertNull($stored->getDepositDate());
    }

    /** @dataProvider kinds */
    public function test_a_settlement_account_from_another_currency_is_refused(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
            'settlement_account_id' => $this->otherCurrencyAccount->id,
        ]);

        $response->assertSessionHas('fail');
        $this->assertSame($deposit->fresh()::RUNNING, $deposit->fresh()->getStatus());
    }

    /**
     * حساب تابع لبنك تاني مايتقبلش ، حتى لو نفس الشركة ونفس العملة.
     *
     * @dataProvider kinds
     */
    public function test_a_settlement_account_from_another_bank_is_refused(string $kind): void
    {
        if (! $this->otherBank) {
            $this->markTestSkipped('Development database has only one financial institution for this company.');
        }

        $otherBankAccount = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->otherBank->id,
            'account_number' => 'TEST-OTHER-BANK-'.uniqid(),
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subYears(3)->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
            'settlement_account_id' => $otherBankAccount->id,
        ]);

        $response->assertSessionHas('fail');

        $stored = $deposit->fresh();
        $this->assertNull($stored->getSettlementAccountId());
        $this->assertSame($stored::RUNNING, $stored->getStatus());
    }

    /** @dataProvider kinds */
    public function test_reversing_the_deposit_clears_the_settlement_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
            'settlement_account_id' => $this->alternativeAccount->id,
        ]);

        $this->assertSame($this->alternativeAccount->id, $deposit->fresh()->getSettlementAccountId());

        $this->actingAs($this->actor)->post($this->reverseDepositUrl($kind, $deposit));

        $stored = $deposit->fresh();
        $this->assertNull($stored->getSettlementAccountId(), 'العكس لازم يشيل حساب التسوية');
        $this->assertSame($stored::RUNNING, $stored->getStatus());
    }

    /* ─────────────────────── Break ─────────────────────── */

    /** @dataProvider kinds */
    public function test_breaking_stores_the_chosen_settlement_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyBreakUrl($kind, $deposit), [
            'break_date' => now()->format('Y-m-d'),
            'break_interest_amount' => 0,
            'amount' => 100000,
            'settlement_account_id' => $this->alternativeAccount->id,
        ]);

        /*
         * مابنعملش assert على فلاش الـ success نفسه: باكدج flasher بيسحب
         * المفتاح ده من السيشن وهو بيحوّله لـ envelope ، فا غيابه مايعنيش
         * فشل. اللي بيهمنا ان مافيش fail وان الحالة اتغيرت فعلا.
         */
        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $stored = $deposit->fresh();
        $this->assertSame($this->alternativeAccount->id, $stored->getSettlementAccountId());
        $this->assertSame($stored::BROKEN, $stored->getStatus());
    }

    /** @dataProvider kinds */
    public function test_breaking_without_a_settlement_account_changes_nothing(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $response = $this->actingAs($this->actor)->post($this->applyBreakUrl($kind, $deposit), [
            'break_date' => now()->format('Y-m-d'),
            'break_interest_amount' => 0,
            'amount' => 100000,
        ]);

        $response->assertSessionHas('fail');

        $stored = $deposit->fresh();
        $this->assertNull($stored->getSettlementAccountId());
        $this->assertSame($stored::RUNNING, $stored->getStatus());
        $this->assertNull($stored->getBreakDate());
    }

    /** @dataProvider kinds */
    public function test_reversing_the_break_clears_the_settlement_account(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, null);

        $this->actingAs($this->actor)->post($this->applyBreakUrl($kind, $deposit), [
            'break_date' => now()->format('Y-m-d'),
            'break_interest_amount' => 0,
            'amount' => 100000,
            'settlement_account_id' => $this->alternativeAccount->id,
        ]);

        $this->assertSame($this->alternativeAccount->id, $deposit->fresh()->getSettlementAccountId());

        $this->actingAs($this->actor)->post($this->reverseBrokenUrl($kind, $deposit));

        $stored = $deposit->fresh();
        $this->assertNull($stored->getSettlementAccountId(), 'عكس الكسر لازم يشيل حساب التسوية');
        $this->assertSame($stored::RUNNING, $stored->getStatus());
    }

    /* ─────────────────── بروبس الصفحة ─────────────────── */

    /**
     * صفحة الليست بتبعت لكل صف الحساب الافتراضي وقايمة الحسابات المسموح
     * بيها ، وده اللي بوب اب الاستحقاق والكسر في الفيو بيتعبوا منه.
     *
     * @dataProvider kinds
     */
    public function test_the_index_page_sends_the_settlement_account_props(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->fundingAccount->id);

        $url = $kind === 'td'
            ? route('view.time.of.deposit', ['company' => $this->company->id, 'financialInstitution' => $this->bank->id])
            : route('view.certificates.of.deposit', ['company' => $this->company->id, 'financialInstitution' => $this->bank->id]);

        $response = $this->actingAs($this->actor)->get($url);
        $response->assertStatus(200);

        $rows = collect(data_get($response->viewData('page'), 'props.deposits.running', []));
        $row = $rows->firstWhere('id', $deposit->id);

        $this->assertNotNull($row, 'الوديعة المفروض تبان في تاب الـ running');
        $this->assertSame($this->fundingAccount->id, $row['settlement_account_id']);

        $offered = collect($row['settlement_account_options'])->pluck('id')->all();
        $this->assertContains($this->fundingAccount->id, $offered);
        $this->assertContains($this->alternativeAccount->id, $offered);
        $this->assertNotContains($this->otherCurrencyAccount->id, $offered);
    }

    /* ───────── الحساب اللي البوب اب هيفتح عليه بعد العكس ───────── */

    /** @dataProvider kinds */
    public function test_after_a_reverse_the_popup_goes_back_to_the_deducted_from_default(string $kind): void
    {
        $deposit = $this->makeDeposit($kind, $this->fundingAccount->id);

        $this->actingAs($this->actor)->post($this->applyDepositUrl($kind, $deposit), [
            'deposit_date' => now()->format('Y-m-d'),
            'actual_interest_amount' => 0,
            'settlement_account_id' => $this->alternativeAccount->id,
        ]);
        $this->actingAs($this->actor)->post($this->reverseDepositUrl($kind, $deposit));

        $this->assertSame(
            $this->fundingAccount->id,
            $deposit->fresh()->getSettlementOrDeductedFromAccountId(),
            'بعد العكس الافتراضي يرجع تاني لحساب الخصم الاصلي'
        );
    }
}

<?php

namespace Tests\Feature\BankStatements;

use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use App\Models\TimeOfDeposit;
use App\Models\User;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * تعديل صف في كشف الحساب لشركة من غير تكامل أودو.
 *
 * الخلفية: الصف اللي عليه interest_journal_entry_id كان بيروح ينشئ
 * CashExpenseOdooService على طول عشان يفك ارتباط القيد. و الشركة اللي
 * ماعندهاش تكامل ماعندهاش odoo_db_url ، و AuthTrait::$url نوعه string
 * مش nullable — فالـ constructor كان بيرمي
 *   TypeError: Cannot assign null to property ...::$url of type string
 * و التعديل كان بيقع بـ 500 من غير ما يتنفّذ أصلا.
 *
 * التست ده بيتأكد إن المسار بيعدّي و الصف بيتحدّث فعلا.
 *
 * الشركة و البنك و الحساب و اليوزر معمولين في التست نفسه (شركة مشتركة في
 * CashVero و من غير أودو) ، و كله بيترجع في الآخر.
 */
class BankStatementRowOdooGuardTest extends TestCase
{
    use MakesBanks;

    private Company $company;

    private FinancialInstitutionAccount $account;

    private User $actor;

    /** ROUTING_LOCALE لازم يتظبط قبل ما التطبيق يقوم — راجع DepositSettlementAccountTest */
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        // شركة مشتركة في CashVero (المسار محطوط خلف isCashManagement) و من غير أودو
        $this->company = $this->makeCompany();

        $this->account = $this->currentAccount($this->bank($this->company), 'EGP', [
            'account_number' => 'TEST-ODOO-GUARD-'.uniqid(),
            'balance_date' => now()->subYears(2)->format('Y-m-d'),
        ]);

        $this->actor = $this->makeUser(['report_bank_statement.update'], $this->company, 'company-admin');
    }

    /**
     * * نفس الباج في مسار الحذف : HasPeriodicInterest::deletePeriodInterest()
     * * كان بينشئ CashExpenseOdooService على طول عشان يفك ارتباط القيد
     */
    public function test_a_period_interest_row_is_deleted_without_odoo_credentials(): void
    {
        $this->assertFalse($this->company->hasOdooIntegrationCredentials($this->actor));

        $deposit = TimeOfDeposit::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->account->financial_institution_id,
            'account_number' => 'TD-ODOO-GUARD-'.uniqid(),
            'amount' => 100000,
            'currency' => 'EGP',
            'interest_rate' => 10,
            'interest_amount' => 1000,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->subDay()->format('Y-m-d'),
            'status' => TimeOfDeposit::RUNNING,
            'maturity_amount_added_to_account_id' => $this->account->id,
            'is_at_maturity' => 1,
            'is_active' => 1,
        ]);

        $statement = CurrentAccountBankStatement::create([
            'company_id' => $this->company->id,
            'financial_institution_account_id' => $this->account->id,
            'time_of_deposit_id' => $deposit->id,
            'date' => now()->subMonth()->format('Y-m-d'),
            'debit' => 500,
            'credit' => 0,
            'beginning_balance' => 0,
            'comment_en' => 'Odoo guard delete test',
            'comment_ar' => 'Odoo guard delete test',
            'interest_journal_entry_id' => 123456789,
        ]);

        $deposit->load('currentAccountBankStatements');
        $deposit->deletePeriodInterest($statement);

        $this->assertNull(CurrentAccountBankStatement::find($statement->id));
        $this->odoo()->assertNothingCalled();
    }

    public function test_a_row_carrying_an_odoo_interest_entry_updates_without_odoo_credentials(): void
    {
        // الشرط اللي كان بيولّع الباج: مفيش بيانات تكامل أودو
        $this->assertFalse($this->company->hasOdooIntegrationCredentials($this->actor));

        $statement = CurrentAccountBankStatement::create([
            'company_id' => $this->company->id,
            'financial_institution_account_id' => $this->account->id,
            'date' => now()->subMonth()->format('Y-m-d'),
            'debit' => 1000,
            'credit' => 0,
            'beginning_balance' => 0,
            'comment_en' => 'Odoo guard test',
            'comment_ar' => 'Odoo guard test',
            'interest_journal_entry_id' => 987654321,
        ]);

        $response = $this->actingAs($this->actor)->post(
            route('update.bank.statement.debit.or.credit', ['company' => $this->company->id]),
            [
                'statement_model_name' => 'CurrentAccountBankStatement',
                'statement_id' => $statement->id,
                'debit' => '2000',
                'credit' => '0',
                'date' => $statement->date,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionMissing('fail');

        $this->assertSame(2000.0, (float) $statement->fresh()->debit);
        $this->odoo()->assertNothingCalled();
    }
}

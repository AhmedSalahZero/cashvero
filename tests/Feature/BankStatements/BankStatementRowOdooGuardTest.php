<?php

namespace Tests\Feature\BankStatements;

use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
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
 */
class BankStatementRowOdooGuardTest extends TestCase
{
    use DatabaseTransactions;

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
            DB::table('companies')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        /*
         * المسار محطوط خلف isCashManagement ، و ده بيقرا انظمة الشركة
         * الاولانية بتاعة اليوزر (company_systems) — فالشركة لازم يكون
         * عندها CASH_VERO و بنك نعلّق عليه الحساب في نفس الوقت
         */
        $companyIds = DB::table('company_systems')->where('system_name', CASH_VERO)->pluck('company_id');
        $bank = FinancialInstitution::query()->whereIn('company_id', $companyIds)->first();

        if (! $bank) {
            $this->markTestSkipped('Development database has no cash-management company with a financial institution.');
        }

        $this->company = Company::findOrFail($bank->company_id);

        $this->account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $bank->id,
            'account_number' => 'TEST-ODOO-GUARD-'.uniqid(),
            'currency' => 'EGP',
            'balance_amount' => 0,
            'balance_date' => now()->subYears(2)->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        /** @var User $user */
        $user = User::create([
            'name' => 'Bank Statement Odoo Guard '.uniqid(),
            'email' => 'bank-statement-odoo-guard-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->assignRole('company-admin');
        /*
         * cashvero عنده EnforcePermission بيخريطة كل راوت لصلاحية
         * (و ده مش موجود في system.veroanalysisb.com) — فبناخد
         * المطلوب من الخريطة نفسها لو موجودة ، عشان نفس الملف يشتغل
         * في المشروعين من غير تفريع
         */
        $mapClass = '\App\Support\Permissions\RoutePermissionMap';
        $required = class_exists($mapClass) ? ($mapClass::for('update.bank.statement.debit.or.credit') ?: []) : [];
        foreach ($required as $permissionName) {
            $permission = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            $user->givePermissionTo($permission);
        }
        $user->load('roles', 'permissions');
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        $this->actor = $user;
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
    }
}

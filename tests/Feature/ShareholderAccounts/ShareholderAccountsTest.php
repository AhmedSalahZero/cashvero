<?php

namespace Tests\Feature\ShareholderAccounts;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\MediumTermLoan;
use App\Models\Partner;
use App\Models\User;
use App\Support\CashDashboard\LatestStatementQuery;
use App\Support\ShareholderAccounts\AccountOwnerFilter;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Shareholder / owner personal accounts — the behaviour that actually
 * touches the database. See docs/shareholder-accounts.md.
 *
 * Runs against the development database like the rest of this suite
 * (there is no migrated test schema here), inside a transaction that is
 * rolled back — every row these tests create is temporary, and they skip
 * themselves when the database is unreachable.
 */
class ShareholderAccountsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    private Partner $shareholder;

    private Partner $otherShareholder;

    private FinancialInstitutionAccount $companyAccount;

    private FinancialInstitutionAccount $shareholderAccount;

    /** A plain user, so the permission gates are really exercised (super admins bypass). */
    private User $userWithPermission;

    private User $userWithoutPermission;

    private string $currency = 'EGP';

    /**
     * DatabaseTransactions opens its transaction from setUpTraits(), which
     * runs before setUp()'s body — so the connection has to be repointed
     * here, or the transaction is opened against the (non-existent) test
     * schema named in phpunit.xml before anything else gets a say.
     */
    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Without this every request 302s on the locale prefix before it
         * reaches a controller — the same guard PaginationSmokeTest
         * documents. Auth and the permission gates stay on.
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

        $this->shareholder = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Test Shareholder A '.uniqid(),
            'is_shareholder' => 1,
        ]);

        $this->otherShareholder = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Test Shareholder B '.uniqid(),
            'is_shareholder' => 1,
        ]);

        $this->companyAccount = $this->makeAccount('TEST-CO-'.uniqid());
        $this->shareholderAccount = $this->makeAccount('TEST-SH-'.uniqid(), $this->shareholder);

        $this->userWithPermission = $this->makeUser(true);
        $this->userWithoutPermission = $this->makeUser(false);
    }

    private function makeAccount(string $accountNumber, ?Partner $owner = null): FinancialInstitutionAccount
    {
        /** @var FinancialInstitutionAccount $account */
        $account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => $accountNumber,
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subYear()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
            'is_shareholder_account' => (bool) $owner,
            'shareholder_partner_id' => $owner?->id,
        ]);

        // One statement row so the dashboard's "latest balance" query has
        // something to find for this account.
        DB::table('current_account_bank_statements')->insert([
            'financial_institution_account_id' => $account->id,
            'company_id' => $this->company->id,
            'date' => now()->subMonth()->format('Y-m-d'),
            'full_date' => now()->subMonth()->format('Y-m-d H:i:s'),
            'is_beginning_balance' => 1,
            'beginning_balance' => 0,
            'debit' => 1000,
            'end_balance' => 1000,
        ]);

        return $account;
    }

    private function makeUser(bool $withPermission): User
    {
        /** @var User $user */
        $user = User::create([
            'name' => 'Shareholder Accounts Test '.uniqid(),
            'email' => 'shareholder-accounts-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);

        /*
         * The page-level permissions both users need, so the only thing
         * that differs between them is the shareholder gate itself —
         * otherwise a 403 would "pass" the hiding assertions for the
         * wrong reason.
         */
        $user->givePermissionTo([
            'dashboard_cash.view',
            'bank_account.view',
            'internal_money_transfer.create',
        ]);

        if ($withPermission) {
            $user->givePermissionTo('shareholder_account.view');
        }

        /*
         * Two gates sit in front of every company page besides the
         * permission itself: canViewCurrentCompany (the user must be
         * attached to this company) and CashManagementMiddleware (that
         * company must have the cash-vero system). Attaching the company
         * satisfies both.
         */
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        $this->assertFalse($user->isSuperAdmin(), 'The test user must not bypass permission checks.');

        return $user;
    }

    private function balancesFor(AccountOwnerFilter $filter): array
    {
        $rows = LatestStatementQuery::latestCurrentAccountBalances(
            $this->company->id,
            now()->format('Y-m-d'),
            [$this->bank->id],
            [$this->currency],
            $filter
        );

        return collect($rows[$this->currency] ?? [])->pluck('account_number')->all();
    }

    /* ───────────────────────── The flag itself ───────────────────── */

    public function test_the_ownership_flag_round_trips(): void
    {
        $this->assertFalse($this->companyAccount->fresh()->isShareholderAccount());
        $this->assertNull($this->companyAccount->fresh()->getShareholderName());

        $stored = $this->shareholderAccount->fresh();
        $this->assertTrue($stored->isShareholderAccount());
        $this->assertSame($this->shareholder->name, $stored->getShareholderName());
    }

    /** A flag with no owner id is not a shareholder account — it is incomplete. */
    public function test_a_flag_without_an_owner_is_not_treated_as_a_shareholder_account(): void
    {
        $this->companyAccount->forceFill([
            'is_shareholder_account' => true,
            'shareholder_partner_id' => null,
        ])->save();

        $this->assertFalse($this->companyAccount->fresh()->isShareholderAccount());
    }

    public function test_scopes_split_company_and_shareholder_accounts(): void
    {
        $companyOwned = FinancialInstitutionAccount::onlyCompanyOwned()
            ->where('company_id', $this->company->id)
            ->pluck('id')
            ->all();

        $this->assertContains($this->companyAccount->id, $companyOwned);
        $this->assertNotContains($this->shareholderAccount->id, $companyOwned);

        $shareholderOwned = FinancialInstitutionAccount::onlyShareholderOwned()
            ->where('company_id', $this->company->id)
            ->pluck('id')
            ->all();

        $this->assertContains($this->shareholderAccount->id, $shareholderOwned);
        $this->assertNotContains($this->companyAccount->id, $shareholderOwned);

        // ...and narrowed to one owner (D3).
        $otherOwnerOnly = FinancialInstitutionAccount::onlyShareholderOwned($this->otherShareholder->id)
            ->where('company_id', $this->company->id)
            ->pluck('id')
            ->all();

        $this->assertNotContains($this->shareholderAccount->id, $otherOwnerOnly);
    }

    /* ─────────────────── D7 — dropdown labels ────────────────────── */

    public function test_the_dropdown_label_names_the_owner_while_the_value_stays_the_account_number(): void
    {
        $this->actingAs($this->userWithPermission);

        $accounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency(
            $this->company->id,
            $this->currency,
            $this->bank->id
        );

        // The KEY is what gets stored — it must remain the bare number.
        $this->assertArrayHasKey($this->shareholderAccount->account_number, $accounts);
        $this->assertArrayHasKey($this->companyAccount->account_number, $accounts);

        // The VALUE is what the user reads.
        $this->assertSame(
            $this->shareholderAccount->account_number.' — '.$this->shareholder->name,
            $accounts[$this->shareholderAccount->account_number]
        );

        // A company account is untouched — no stray separator.
        $this->assertSame(
            $this->companyAccount->account_number,
            $accounts[$this->companyAccount->account_number]
        );
    }

    /* ─────────────────── D6 — the permission ─────────────────────── */

    public function test_the_dropdown_hides_shareholder_accounts_without_the_permission(): void
    {
        $this->actingAs($this->userWithoutPermission);

        $accounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency(
            $this->company->id,
            $this->currency,
            $this->bank->id
        );

        $this->assertArrayHasKey($this->companyAccount->account_number, $accounts);
        $this->assertArrayNotHasKey($this->shareholderAccount->account_number, $accounts);
    }

    public function test_the_bank_accounts_list_hides_shareholder_rows_without_the_permission(): void
    {
        $withPermission = $this->actingAs($this->userWithPermission)
            ->get(route('view.all.bank.accounts', [
                'company' => $this->company->id,
                'financialInstitution' => $this->bank->id,
            ]));

        $withPermission->assertStatus(200);
        $withPermission->assertSee($this->shareholderAccount->account_number);

        $withoutPermission = $this->actingAs($this->userWithoutPermission)
            ->get(route('view.all.bank.accounts', [
                'company' => $this->company->id,
                'financialInstitution' => $this->bank->id,
            ]));

        $withoutPermission->assertStatus(200);
        $withoutPermission->assertDontSee($this->shareholderAccount->account_number);
        $withoutPermission->assertSee($this->companyAccount->account_number);
    }

    /* ─────────────── D2 / D3 — the dashboard filter ──────────────── */

    public function test_company_accounts_is_the_default_and_excludes_owner_accounts(): void
    {
        $defaulted = $this->balancesFor(AccountOwnerFilter::make(null));

        $this->assertContains($this->companyAccount->account_number, $defaulted);
        $this->assertNotContains($this->shareholderAccount->account_number, $defaulted);
    }

    public function test_all_accounts_returns_both_company_and_owner_accounts(): void
    {
        $all = $this->balancesFor(AccountOwnerFilter::make('all'));

        $this->assertContains($this->companyAccount->account_number, $all);
        $this->assertContains($this->shareholderAccount->account_number, $all);
    }

    public function test_shareholders_accounts_returns_only_owner_accounts(): void
    {
        $shareholders = $this->balancesFor(AccountOwnerFilter::make('shareholders'));

        $this->assertContains($this->shareholderAccount->account_number, $shareholders);
        $this->assertNotContains($this->companyAccount->account_number, $shareholders);
    }

    public function test_a_single_shareholder_can_be_isolated(): void
    {
        $mine = $this->balancesFor(AccountOwnerFilter::make('shareholders', $this->shareholder->id));
        $this->assertContains($this->shareholderAccount->account_number, $mine);

        $theirs = $this->balancesFor(AccountOwnerFilter::make('shareholders', $this->otherShareholder->id));
        $this->assertNotContains($this->shareholderAccount->account_number, $theirs);
    }

    public function test_the_cash_status_dashboard_loads_under_every_filter(): void
    {
        foreach ([[], ['account_owner' => 'all'], ['account_owner' => 'company'],
            ['account_owner' => 'shareholders'],
            ['account_owner' => 'shareholders', 'shareholder_partner_id' => $this->shareholder->id]] as $query) {
            $response = $this->actingAs($this->userWithPermission)
                ->get(route('view.customer.invoice.dashboard.cash', ['company' => $this->company->id] + $query));

            $response->assertStatus(200);
        }
    }

    /* ───────── D1 — the filter is by ownership, uniformly ────────── */

    public function test_a_shareholder_flagged_loan_follows_the_filter_like_any_other_account(): void
    {
        /** @var MediumTermLoan $loan */
        $loan = MediumTermLoan::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'name' => 'Owner personal MTL '.uniqid(),
            'currency' => $this->currency,
            'limit' => 500000,
            'account_number' => 'TEST-MTL-'.uniqid(),
            'status' => MediumTermLoan::RUNNING,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'is_shareholder_account' => true,
            'shareholder_partner_id' => $this->shareholder->id,
        ]);

        $loansUnder = function (array $query) {
            $request = \Illuminate\Http\Request::create('/', 'GET', $query + ['currencies' => [$this->currency]]);

            return collect(
                app(\App\Services\CashDashboardService::class)
                    ->build($this->company, $request)['mediumTermLoansArr'][$this->currency] ?? collect()
            )->pluck('id')->all();
        };

        $this->actingAs($this->userWithPermission);

        /*
         * D1: the filter means ownership and nothing else, applied the same
         * way to every instrument. An owner's loan is the owner's, exactly
         * like their current account or their TD — so it appears under
         * All and Shareholders, and not under Company.
         */
        $this->assertNotContains($loan->id, $loansUnder([]), 'The default (company) view must not carry an owner loan.');
        $this->assertNotContains($loan->id, $loansUnder(['account_owner' => 'company']));
        $this->assertContains($loan->id, $loansUnder(['account_owner' => 'all']));
        $this->assertContains($loan->id, $loansUnder(['account_owner' => 'shareholders']));
        $this->assertContains($loan->id, $loansUnder([
            'account_owner' => 'shareholders',
            'shareholder_partner_id' => $this->shareholder->id,
        ]));
        $this->assertNotContains($loan->id, $loansUnder([
            'account_owner' => 'shareholders',
            'shareholder_partner_id' => $this->otherShareholder->id,
        ]));
    }

    /** A company-owned loan is the mirror image: everywhere except the owner view. */
    public function test_a_company_loan_shows_under_company_and_all_but_not_under_shareholders(): void
    {
        /** @var MediumTermLoan $loan */
        $loan = MediumTermLoan::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'name' => 'Company MTL '.uniqid(),
            'currency' => $this->currency,
            'limit' => 250000,
            'account_number' => 'TEST-MTL-CO-'.uniqid(),
            'status' => MediumTermLoan::RUNNING,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $loansUnder = function (array $query) {
            $request = \Illuminate\Http\Request::create('/', 'GET', $query + ['currencies' => [$this->currency]]);

            return collect(
                app(\App\Services\CashDashboardService::class)
                    ->build($this->company, $request)['mediumTermLoansArr'][$this->currency] ?? collect()
            )->pluck('id')->all();
        };

        $this->actingAs($this->userWithPermission);

        $this->assertContains($loan->id, $loansUnder([]));
        $this->assertContains($loan->id, $loansUnder(['account_owner' => 'company']));
        $this->assertContains($loan->id, $loansUnder(['account_owner' => 'all']));
        $this->assertNotContains($loan->id, $loansUnder(['account_owner' => 'shareholders']));
    }

    /* ───────── D4 — transfers do not touch the owner ledger ───────── */

    public function test_an_internal_transfer_to_a_shareholder_account_writes_no_ledger_row(): void
    {
        $before = DB::table('shareholder_statements')
            ->where('company_id', $this->company->id)
            ->count();

        $response = $this->actingAs($this->userWithPermission)
            ->post(route('internal-money-transfers.store', [
                'company' => $this->company->id,
                'type' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
            ]), [
                // The real form posts company_id — storeBasicForm() only
                // writes columns the request actually carries.
                'company_id' => $this->company->id,
                'transfer_date' => now()->format('Y-m-d'),
                'transfer_days' => 0,
                'amount' => 100,
                'currency' => $this->currency,
                'from_bank_id' => $this->bank->id,
                'from_account_type_id' => $this->currentAccountTypeId(),
                'from_account_number' => $this->companyAccount->account_number,
                'to_bank_id' => $this->bank->id,
                'to_account_type_id' => $this->currentAccountTypeId(),
                'to_account_number' => $this->shareholderAccount->account_number,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(
            $response->isRedirect() || $response->isSuccessful(),
            'The transfer POST was rejected with status '.$response->getStatusCode()
                .' '.optional($response->exception)->getMessage()
        );

        $this->assertDatabaseHas('internal_money_transfers', [
            'company_id' => $this->company->id,
            'from_account_number' => $this->companyAccount->account_number,
            'to_account_number' => $this->shareholderAccount->account_number,
        ]);

        $after = DB::table('shareholder_statements')
            ->where('company_id', $this->company->id)
            ->count();

        $this->assertSame(
            $before,
            $after,
            'A company→shareholder transfer must stay a pure cash movement in v1 (decision D4).'
        );
    }

    public function test_shareholder_mtl_500k_cycle_moves_cash_to_company_without_moving_the_loan_to_company_view(): void
    {
        $companyAccount = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-CYCLE-CO-'.uniqid(),
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subDay()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
            'is_shareholder_account' => false,
            'shareholder_partner_id' => null,
        ]);

        $shareholderAccount = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-CYCLE-SH-'.uniqid(),
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subDay()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
            'is_shareholder_account' => true,
            'shareholder_partner_id' => $this->shareholder->id,
        ]);

        DB::table('current_account_bank_statements')->insert([
            [
                'financial_institution_account_id' => $companyAccount->id,
                'company_id' => $this->company->id,
                'date' => now()->subDay()->format('Y-m-d'),
                'full_date' => now()->subDay()->format('Y-m-d H:i:s'),
                'is_beginning_balance' => 1,
                'beginning_balance' => 0,
                'debit' => 0,
                'end_balance' => 0,
            ],
            [
                'financial_institution_account_id' => $shareholderAccount->id,
                'company_id' => $this->company->id,
                'date' => now()->subDay()->format('Y-m-d'),
                'full_date' => now()->subDay()->format('Y-m-d H:i:s'),
                'is_beginning_balance' => 1,
                'beginning_balance' => 0,
                'debit' => 500000,
                'end_balance' => 500000,
            ],
        ]);

        /** @var MediumTermLoan $loan */
        $loan = MediumTermLoan::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'name' => 'Owner cycle MTL '.uniqid(),
            'currency' => $this->currency,
            'limit' => 500000,
            'account_number' => 'TEST-CYCLE-MTL-'.uniqid(),
            'status' => MediumTermLoan::RUNNING,
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'is_shareholder_account' => true,
            'shareholder_partner_id' => $this->shareholder->id,
        ]);

        $response = $this->actingAs($this->userWithPermission)
            ->post(route('internal-money-transfers.store', [
                'company' => $this->company->id,
                'type' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
            ]), [
                'company_id' => $this->company->id,
                'transfer_date' => now()->format('Y-m-d'),
                'transfer_days' => 0,
                'amount' => 500000,
                'currency' => $this->currency,
                'from_bank_id' => $this->bank->id,
                'from_account_type_id' => $this->currentAccountTypeId(),
                'from_account_number' => $shareholderAccount->account_number,
                'to_bank_id' => $this->bank->id,
                'to_account_type_id' => $this->currentAccountTypeId(),
                'to_account_number' => $companyAccount->account_number,
            ]);

        $response->assertSessionHasNoErrors();

        $companyRows = $this->latestRowsFor(AccountOwnerFilter::make('company'));
        $allRows = $this->latestRowsFor(AccountOwnerFilter::make('all'));
        $shareholderRows = $this->latestRowsFor(AccountOwnerFilter::make('shareholders', $this->shareholder->id));

        $companyKey = $this->bank->id.'|'.$companyAccount->account_number;
        $shareholderKey = $this->bank->id.'|'.$shareholderAccount->account_number;

        $this->assertArrayHasKey($companyKey, $companyRows);
        $this->assertArrayNotHasKey($shareholderKey, $companyRows);
        $this->assertSame(500000.0, (float) $companyRows[$companyKey]->end_balance);

        $this->assertArrayHasKey($companyKey, $allRows);
        $this->assertArrayHasKey($shareholderKey, $allRows);
        $this->assertSame(500000.0, (float) $allRows[$companyKey]->end_balance);
        $this->assertSame(0.0, (float) $allRows[$shareholderKey]->end_balance);

        $this->assertArrayNotHasKey($companyKey, $shareholderRows);
        $this->assertArrayHasKey($shareholderKey, $shareholderRows);
        $this->assertSame(0.0, (float) $shareholderRows[$shareholderKey]->end_balance);

        $this->assertNotContains($loan->id, $this->loanIdsUnder([]));
        $this->assertContains($loan->id, $this->loanIdsUnder(['account_owner' => 'all']));
        $this->assertContains($loan->id, $this->loanIdsUnder([
            'account_owner' => 'shareholders',
            'shareholder_partner_id' => $this->shareholder->id,
        ]));
    }

    public function test_an_internal_transfer_from_shareholder_to_company_writes_no_ledger_row(): void
    {
        $before = DB::table('shareholder_statements')
            ->where('company_id', $this->company->id)
            ->count();

        $response = $this->actingAs($this->userWithPermission)
            ->post(route('internal-money-transfers.store', [
                'company' => $this->company->id,
                'type' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
            ]), [
                'company_id' => $this->company->id,
                'transfer_date' => now()->format('Y-m-d'),
                'transfer_days' => 0,
                'amount' => 100,
                'currency' => $this->currency,
                'from_bank_id' => $this->bank->id,
                'from_account_type_id' => $this->currentAccountTypeId(),
                'from_account_number' => $this->shareholderAccount->account_number,
                'to_bank_id' => $this->bank->id,
                'to_account_type_id' => $this->currentAccountTypeId(),
                'to_account_number' => $this->companyAccount->account_number,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(
            $response->isRedirect() || $response->isSuccessful(),
            'The transfer POST was rejected with status '.$response->getStatusCode()
                .' '.optional($response->exception)->getMessage()
        );

        $this->assertDatabaseHas('internal_money_transfers', [
            'company_id' => $this->company->id,
            'from_account_number' => $this->shareholderAccount->account_number,
            'to_account_number' => $this->companyAccount->account_number,
        ]);

        $after = DB::table('shareholder_statements')
            ->where('company_id', $this->company->id)
            ->count();

        $this->assertSame(
            $before,
            $after,
            'A shareholder→company transfer must stay a pure cash movement in v1 (decision D4).'
        );
    }

    /* ───────── Display: FI create owner + labeled account numbers ─ */

    public function test_financial_institution_create_exposes_shareholder_owner_fields_when_permitted(): void
    {
        $this->userWithPermission->givePermissionTo('financial_institution.create');

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('create.financial.institutions', ['company' => $this->company->id]));

        $response->assertOk();
        $response->assertSee('canManageShareholderAccounts', false);
        $response->assertSee($this->shareholder->name, false);
    }

    public function test_financial_institution_create_hides_shareholders_without_the_permission(): void
    {
        $this->userWithoutPermission->givePermissionTo('financial_institution.create');

        $response = $this->actingAs($this->userWithoutPermission)
            ->get(route('create.financial.institutions', ['company' => $this->company->id]));

        $response->assertOk();
        $response->assertDontSee($this->shareholder->name, false);
    }

    public function test_internal_money_transfer_index_shows_the_shareholder_name_beside_the_account_number(): void
    {
        $this->userWithPermission->givePermissionTo('internal_money_transfer.view');

        $this->actingAs($this->userWithPermission)
            ->post(route('internal-money-transfers.store', [
                'company' => $this->company->id,
                'type' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
            ]), [
                'company_id' => $this->company->id,
                'transfer_date' => now()->format('Y-m-d'),
                'transfer_days' => 0,
                'amount' => 100,
                'currency' => $this->currency,
                'from_bank_id' => $this->bank->id,
                'from_account_type_id' => $this->currentAccountTypeId(),
                'from_account_number' => $this->companyAccount->account_number,
                'to_bank_id' => $this->bank->id,
                'to_account_type_id' => $this->currentAccountTypeId(),
                'to_account_number' => $this->shareholderAccount->account_number,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('internal_money_transfers', [
            'company_id' => $this->company->id,
            'from_account_number' => $this->companyAccount->account_number,
            'to_account_number' => $this->shareholderAccount->account_number,
        ]);

        $expected = \App\Support\ShareholderAccounts\AccountNumberLabel::format(
            $this->shareholderAccount->account_number,
            $this->shareholder->name
        );

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('internal-money-transfers.index', [
                'company' => $this->company->id,
                'active' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
                'value' => $this->shareholderAccount->account_number,
            ]));

        $response->assertOk();
        $toNumbers = collect($response->inertiaProps('bankToBankTab.rows.data'))
            ->pluck('to_account_number');
        $fromNumbers = collect($response->inertiaProps('bankToBankTab.rows.data'))
            ->pluck('from_account_number');

        $this->assertTrue(
            $toNumbers->contains($expected),
            'The internal transfer index must show the shareholder name beside the account number.'
        );
        $this->assertTrue($fromNumbers->contains($this->companyAccount->account_number));
        $this->assertFalse(
            $fromNumbers->contains($this->companyAccount->account_number.' — '.$this->shareholder->name)
        );
    }

    public function test_bank_statement_result_shows_the_shareholder_name_beside_the_account_number(): void
    {
        $this->userWithPermission->givePermissionTo('report_bank_statement.view');

        $expected = \App\Support\ShareholderAccounts\AccountNumberLabel::format(
            $this->shareholderAccount->account_number,
            $this->shareholder->name
        );

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('result.bank.statement', [
                'company' => $this->company->id,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'financial_institution_id' => $this->bank->id,
                'account_type' => $this->currentAccountTypeId(),
                'account_number' => $this->shareholderAccount->account_number,
                'currency' => $this->currency,
            ]));

        $response->assertOk();
        $this->assertSame($expected, $response->inertiaProps('accountNumber'));
    }

    public function test_bank_statement_comments_name_the_shareholder_when_the_counterparty_is_their_account(): void
    {
        $this->userWithPermission->givePermissionTo('report_bank_statement.view');

        $this->actingAs($this->userWithPermission)
            ->post(route('internal-money-transfers.store', [
                'company' => $this->company->id,
                'type' => \App\Models\InternalMoneyTransfer::BANK_TO_BANK,
            ]), [
                'company_id' => $this->company->id,
                'transfer_date' => now()->format('Y-m-d'),
                'transfer_days' => 0,
                'amount' => 100,
                'currency' => $this->currency,
                'from_bank_id' => $this->bank->id,
                'from_account_type_id' => $this->currentAccountTypeId(),
                'from_account_number' => $this->shareholderAccount->account_number,
                'to_bank_id' => $this->bank->id,
                'to_account_type_id' => $this->currentAccountTypeId(),
                'to_account_number' => $this->companyAccount->account_number,
            ])
            ->assertSessionHasNoErrors();

        $expected = \App\Support\ShareholderAccounts\AccountNumberLabel::format(
            $this->shareholderAccount->account_number,
            $this->shareholder->name
        );

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('result.bank.statement', [
                'company' => $this->company->id,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'financial_institution_id' => $this->bank->id,
                'account_type' => $this->currentAccountTypeId(),
                'account_number' => $this->companyAccount->account_number,
                'currency' => $this->currency,
            ]));

        $response->assertOk();
        $this->assertSame($this->companyAccount->account_number, $response->inertiaProps('accountNumber'));

        $comments = collect($response->inertiaProps('paginator.data'))->pluck('comment');
        $this->assertTrue(
            $comments->contains(fn ($comment) => is_string($comment) && str_contains($comment, $expected)),
            'A statement comment that mentions a shareholder account must include the shareholder name.'
        );
    }

    public function test_safe_statement_comments_name_the_shareholder_when_the_counterparty_is_their_account(): void
    {
        $this->userWithPermission->givePermissionTo('report_safe_statement.view');

        $branch = \App\Models\Branch::query()
            ->where('company_id', $this->company->id)
            ->first();

        if (! $branch) {
            $branch = \App\Models\Branch::create([
                'company_id' => $this->company->id,
                'name' => 'Safe Statement Test Branch '.uniqid(),
            ]);
        }

        $rawComment = 'From '.$this->bank->getName().' Account No '.$this->shareholderAccount->account_number;
        $imtId = DB::table('internal_money_transfers')->insertGetId([
            'company_id' => $this->company->id,
            'type' => \App\Models\InternalMoneyTransfer::BANK_TO_SAFE,
            'transfer_date' => now()->format('Y-m-d'),
            'transfer_days' => 0,
            'amount' => 50,
            'currency' => $this->currency,
            'from_bank_id' => $this->bank->id,
            'from_account_type_id' => $this->currentAccountTypeId(),
            'from_account_number' => $this->shareholderAccount->account_number,
            'to_branch_id' => $branch->id,
            'from_comment_en' => $rawComment,
            'from_comment_ar' => $rawComment,
            'to_comment_en' => $rawComment,
            'to_comment_ar' => $rawComment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cash_in_safe_statements')->insert([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'currency' => $this->currency,
            'exchange_rate' => 1,
            'is_debit' => 1,
            'is_credit' => 0,
            'internal_money_transfer_id' => $imtId,
            'date' => now()->format('Y-m-d'),
            'full_date' => now()->format('Y-m-d H:i:s'),
            'beginning_balance' => 0,
            'debit' => 50,
            'credit' => 0,
            'end_balance' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expected = \App\Support\ShareholderAccounts\AccountNumberLabel::format(
            $this->shareholderAccount->account_number,
            $this->shareholder->name
        );

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('result.safe.statement', [
                'company' => $this->company->id,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'branch_id' => $branch->id,
                'currency' => $this->currency,
            ]));

        $response->assertOk();
        $this->assertSame($branch->name, $response->inertiaProps('branchName'));

        $comments = collect($response->inertiaProps('paginator.data'))->pluck('comment');
        $this->assertTrue(
            $comments->contains(fn ($comment) => is_string($comment) && str_contains($comment, $expected)),
            'A safe-statement comment that mentions a shareholder account must include the shareholder name.'
        );
    }

    public function test_partners_statement_comments_name_the_shareholder_when_the_account_is_theirs(): void
    {
        $this->userWithPermission->givePermissionTo('report_partners_statement.view');

        $rawNumber = $this->shareholderAccount->account_number;
        $rawComment = 'Received In [ '.$this->bank->getName().' ] [ Current Account ] [ '.$rawNumber.' ]';

        DB::table('shareholder_statements')->insert([
            'company_id' => $this->company->id,
            'partner_id' => $this->shareholder->id,
            'currency_name' => $this->currency,
            'is_debit' => 0,
            'is_credit' => 1,
            'date' => now()->format('Y-m-d'),
            'full_date' => now()->format('Y-m-d H:i:s'),
            'beginning_balance' => 0,
            'debit' => 0,
            'credit' => 100,
            'end_balance' => 100,
            'comment_en' => $rawComment,
            'comment_ar' => $rawComment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expected = \App\Support\ShareholderAccounts\AccountNumberLabel::format(
            $rawNumber,
            $this->shareholder->name
        );

        $response = $this->actingAs($this->userWithPermission)
            ->get(route('result.partners.statement', [
                'company' => $this->company->id,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'partner_type' => 'is_shareholder',
                'partner_id' => [$this->shareholder->id],
                'currency' => $this->currency,
            ]));

        $response->assertOk();

        $comments = collect($response->inertiaProps('paginator.data'))
            ->flatMap(fn ($group) => collect($group['rows'] ?? [])->pluck('comment'));

        $this->assertTrue(
            $comments->contains(fn ($comment) => is_string($comment) && str_contains($comment, $expected)),
            'A partner-statement comment that mentions a shareholder account must include the shareholder name.'
        );
        $this->assertFalse(
            $comments->contains(fn ($comment) => is_string($comment) && preg_match('/\[\s*'.preg_quote($rawNumber, '/').'\s*\]/', $comment) === 1 && ! str_contains($comment, $expected)),
            'The raw shareholder account number must not appear unlabeled in the partner-statement comment.'
        );
    }

    /** @return array<string, object> */
    private function latestRowsFor(AccountOwnerFilter $filter): array
    {
        $rows = LatestStatementQuery::latestCurrentAccountBalances(
            $this->company->id,
            now()->format('Y-m-d'),
            [$this->bank->id],
            [$this->currency],
            $filter
        )[$this->currency] ?? [];

        return $rows instanceof \Illuminate\Support\Collection ? $rows->all() : $rows;
    }

    /** @return array<int, int> */
    private function loanIdsUnder(array $query): array
    {
        $request = Request::create('/', 'GET', $query + ['currencies' => [$this->currency]]);

        return collect(
            app(\App\Services\CashDashboardService::class)
                ->build($this->company, $request)['mediumTermLoansArr'][$this->currency] ?? collect()
        )->pluck('id')->all();
    }

    private function currentAccountTypeId(): int
    {
        return (int) \App\Models\AccountType::onlyCurrentAccount()->value('id');
    }
}

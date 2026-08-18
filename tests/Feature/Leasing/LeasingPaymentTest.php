<?php

namespace Tests\Feature\Leasing;

use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Models\LeasingContractBankStatement;
use App\Models\MoneyPayment;
use App\Models\Partner;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Through Leasing" — the money type where the LEASING COMPANY pays the
 * supplier straight out of a leasing contract.
 *
 * Everything here goes through the real HTTP stack: the create screen,
 * the contracts endpoint the card calls, the real store route with a
 * real validated payload, and the index tab. That is deliberate — an
 * earlier attempt at this feature was covered only at the model level
 * and the Money Payment screen itself still fatalled, so a test that
 * never loads or submits the form proves nothing.
 *
 * Runs against the development database; every row it creates is torn
 * down. Point it elsewhere with SMOKE_DB=<name>; it skips itself when
 * the database is unreachable, matching PaginationSmokeTest.
 */
class LeasingPaymentTest extends TestCase
{
    private const LIMIT = 1000000.0;

    private ?Company $company = null;

    private ?User $actor = null;

    private ?LeasingCompany $leasingCompany = null;

    private ?LeasingCompany $otherLeasingCompany = null;

    private ?LeasingContract $contract = null;

    private ?Partner $partner = null;

    private ?FinancialInstitution $bank = null;

    private ?FinancialInstitutionAccount $currentAccount = null;

    private array $createdPermissionIds = [];

    /**
     * ⚠️ The whole application lives under a `/{locale}` prefix, and that
     * prefix is `LaravelLocalization::setLocale()` evaluated ONCE while
     * routes are being registered. From the CLI there is no request to
     * read a locale out of, so it comes back null and every route
     * registers WITHOUT the prefix — which both changes the URLs and
     * moves `Request()->segment(2)`, the expression getCurrentCompanyId()
     * (and therefore StoreMoneyPaymentRequest) uses to find the company.
     * Left alone, the store request fails on a "company id" of
     * "money-payment" and this class would pass or fail for reasons that
     * have nothing to do with leasing.
     *
     * So: bind a request that has a locale in it, then re-register the
     * routes against it. The result is the same URL shape a browser
     * produces.
     */
    public function createApplication()
    {
        // ROUTING_LOCALE is laravel-localization's own hook for exactly
        // this — it is what the package's per-locale route caching uses
        // when there is no request to read a locale from.
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = 'en';
        $_SERVER['ROUTING_LOCALE'] = 'en';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        // Without this the locale middleware 302s every request before a
        // controller runs and the suite would pass while exercising
        // nothing. Auth and the permission middleware stay on.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = Company::first();

        if (! $this->company) {
            $this->markTestSkipped('Development database has no company to exercise.');
        }

        $this->leasingCompany = LeasingCompany::create([
            'company_id' => $this->company->id,
            'name' => 'TEST Leasing Co '.bin2hex(random_bytes(4)),
        ]);

        $this->otherLeasingCompany = LeasingCompany::create([
            'company_id' => $this->company->id,
            'name' => 'TEST Other Leasing Co '.bin2hex(random_bytes(4)),
        ]);

        $this->contract = LeasingContract::create([
            'company_id' => $this->company->id,
            'leasing_company_id' => $this->leasingCompany->id,
            'status' => LeasingContract::RUNNING,
            'name' => 'TEST-LC-'.bin2hex(random_bytes(4)),
            'start_date' => now()->subMonths(2)->format('Y-m-d'),
            'end_date' => now()->addYears(3)->format('Y-m-d'),
            'currency' => 'egp',
            'limit' => self::LIMIT,
            'borrowing_rate' => 10,
            'margin_rate' => 2,
            'duration' => 36,
            'installment_payment_interval' => 'monthly',
        ]);

        $this->partner = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'TEST Partner '.bin2hex(random_bytes(4)),
            'is_other_partner' => 1,
        ]);

        $this->actor = $this->makeActor();
    }

    protected function tearDown(): void
    {
        // Payments first: deleting one cascades its ledger row and its
        // leasing_payments row through the model's own delete path.
        if ($this->contract) {
            $paymentIds = DB::table('leasing_payments')
                ->where('leasing_contract_id', $this->contract->id)
                ->pluck('money_payment_id');

            foreach (MoneyPayment::whereIn('id', $paymentIds)->get() as $payment) {
                $payment->deleteRelations();
                $payment->delete();
            }

            DB::table('leasing_contract_bank_statements')->where('leasing_contract_id', $this->contract->id)->delete();
            DB::table('contract_loan_schedules')->where('leasing_contract_id', $this->contract->id)->delete();
            LeasingContract::where('id', $this->contract->id)->delete();
        }

        if ($this->currentAccount) {
            DB::table('current_account_bank_statements')
                ->where('financial_institution_account_id', $this->currentAccount->id)
                ->delete();
            FinancialInstitutionAccount::where('id', $this->currentAccount->id)->delete();
        }

        if ($this->bank) {
            FinancialInstitution::where('id', $this->bank->id)->delete();
        }

        if ($this->partner) {
            DB::table('other_partner_statements')->where('partner_id', $this->partner->id)->delete();
            Partner::where('id', $this->partner->id)->delete();
        }

        foreach ([$this->leasingCompany, $this->otherLeasingCompany] as $leasingCompany) {
            if ($leasingCompany) {
                LeasingCompany::where('id', $leasingCompany->id)->delete();
            }
        }

        if ($this->actor) {
            DB::table('companies_users')->where('user_id', $this->actor->id)->delete();
            DB::table('model_has_permissions')->where('model_id', $this->actor->id)->delete();
            User::withoutEvents(fn () => User::where('id', $this->actor->id)->forceDelete());
        }

        if ($this->createdPermissionIds) {
            Permission::whereIn('id', $this->createdPermissionIds)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        parent::tearDown();
    }

    /* ── the screen ────────────────────────────────────────────────── */

    public function test_the_money_type_dropdown_offers_through_leasing(): void
    {
        $response = $this->actingAs($this->actor)
            ->get($this->url('money-payment/create'));

        $response->assertOk();

        $props = $this->inertiaProps($response);
        $values = array_column($props['moneyTypes'], 'value');

        $this->assertContains(
            MoneyPayment::LEASING,
            $values,
            'The Money Type dropdown must offer "Through Leasing".'
        );

        $this->assertContains(
            $this->leasingCompany->getName(),
            array_column($props['leasingCompanies'], 'name'),
            'The card needs its leasing companies before the user switches to it.'
        );

        /**
         * ⚠️ The card fetches its contracts from props.urls, so a missing
         * entry leaves it calling `undefined` and the Contract Name
         * dropdown silently stays empty — which is exactly what happened
         * when this url was added to the DOWN PAYMENT form's url list by
         * mistake. Both lists end in the same line, so only asserting on
         * the page itself catches it.
         */
        $this->assertArrayHasKey(
            'getLeasingContracts',
            $props['urls'],
            'The form must tell the card where to fetch contracts from.'
        );
    }

    /**
     * The endpoint reached the way the SCREEN reaches it — through the
     * url the page published, not one the test built for itself.
     */
    public function test_the_contracts_endpoint_is_reachable_at_the_url_the_page_publishes(): void
    {
        $props = $this->inertiaProps(
            $this->actingAs($this->actor)->get($this->url('money-payment/create'))
        );

        $response = $this->actingAs($this->actor)->getJson($props['urls']['getLeasingContracts'].'?'.http_build_query([
            'leasingCompanyId' => $this->leasingCompany->id,
            'currency' => 'egp',
        ]));

        $response->assertOk();

        $this->assertContains(
            $this->contract->id,
            array_column($response->json('contracts'), 'id')
        );
    }

    public function test_the_contracts_endpoint_returns_running_contracts_with_their_room(): void
    {
        $response = $this->actingAs($this->actor)->getJson($this->url('money-payment/get-leasing-contracts').'?'.http_build_query([
            'leasingCompanyId' => $this->leasingCompany->id,
            'currency' => 'egp',
            'date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();

        $contracts = collect($response->json('contracts'));
        $row = $contracts->firstWhere('id', $this->contract->id);

        $this->assertNotNull($row, 'The running contract must be selectable.');
        $this->assertEquals(self::LIMIT, $row['available_room'], 'An untouched contract has its whole limit available.');
    }

    /**
     * A contract belongs to exactly one leasing company, so the second
     * dropdown must never show another company's contracts — otherwise
     * the user picks one and the server rejects it for no visible
     * reason.
     */
    public function test_the_contracts_endpoint_hides_another_companys_contracts(): void
    {
        $response = $this->actingAs($this->actor)->getJson($this->url('money-payment/get-leasing-contracts').'?'.http_build_query([
            'leasingCompanyId' => $this->otherLeasingCompany->id,
            'currency' => 'egp',
        ]));

        $response->assertOk();

        $this->assertNotContains(
            $this->contract->id,
            array_column($response->json('contracts'), 'id')
        );
    }

    /* ── the payment ───────────────────────────────────────────────── */

    public function test_paying_through_leasing_draws_the_contract_down(): void
    {
        $amount = 300000.0;

        $this->postPayment($amount)->assertSessionHasNoErrors();

        $payment = $this->lastPayment();

        $this->assertNotNull($payment, 'The payment must be stored.');
        $this->assertSame(MoneyPayment::LEASING, $payment->getType());

        // The relation row carries the two fields the card asked for…
        $this->assertSame($this->leasingCompany->id, $payment->leasingPayment->leasing_company_id);
        $this->assertSame($this->contract->id, $payment->leasingPayment->leasing_contract_id);

        // …and NOTHING from the bank cascade was created.
        $this->assertNull($payment->outgoingTransfer, 'A leasing payment is not an outgoing transfer.');
        $this->assertNull($payment->payableCheque, 'A leasing payment is not a cheque.');
        $this->assertNull($payment->cashPayment, 'A leasing payment is not a cash payment.');

        /**
         * The ledger row is the point of the feature. Every figure below
         * except `credit` is written by the MySQL triggers, so this also
         * proves the triggers are installed and keyed correctly.
         */
        $row = LeasingContractBankStatement::where('money_payment_id', $payment->id)->first();

        $this->assertNotNull($row, 'The drawdown must reach the contract ledger.');
        $this->assertEquals($amount, (float) $row->credit, 'A drawdown is a credit.');
        $this->assertEquals(0.0, (float) $row->debit);
        $this->assertEquals(self::LIMIT, (float) $row->limit, 'The limit is stamped from the contract itself.');
        $this->assertEquals(0.0, (float) $row->beginning_balance, 'First movement on the contract.');
        $this->assertEquals(-$amount, (float) $row->end_balance, 'Drawing makes the balance negative.');
        $this->assertEquals(self::LIMIT - $amount, (float) $row->room, 'room = limit + end_balance.');

        $this->assertEquals(
            self::LIMIT - $amount,
            $this->contract->fresh()->getAvailableRoomAt(),
            'The contract must report the reduced room.'
        );
    }

    public function test_a_second_payment_continues_from_the_first(): void
    {
        $this->postPayment(300000.0)->assertSessionHasNoErrors();
        $this->postPayment(200000.0)->assertSessionHasNoErrors();

        $rows = LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)
            ->orderByRaw('full_date asc , id asc')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals(-300000.0, (float) $rows[1]->beginning_balance, 'The second row opens where the first closed.');
        $this->assertEquals(-500000.0, (float) $rows[1]->end_balance);
        $this->assertEquals(self::LIMIT - 500000.0, (float) $rows[1]->room);

        $this->assertEquals(self::LIMIT - 500000.0, $this->contract->fresh()->getAvailableRoomAt());
    }

    public function test_a_payment_bigger_than_the_remaining_room_is_refused(): void
    {
        $this->postPayment(700000.0)->assertSessionHasNoErrors();

        // 400,000 left; asking for 500,000.
        $this->postPayment(500000.0)->assertSessionHasErrors('leasing_contract_id');

        $this->assertSame(
            1,
            LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)->count(),
            'The refused payment must leave no trace on the ledger.'
        );
    }

    /**
     * The room is read AT THE PAYMENT DATE, so a payment dated before an
     * existing drawdown is checked against what was available back then,
     * not against today's balance.
     */
    public function test_the_room_is_measured_at_the_payment_date(): void
    {
        $this->postPayment(900000.0, now()->format('Y-m-d'))->assertSessionHasNoErrors();

        // Only 100,000 is left today, but on this earlier date the whole
        // limit was still untouched.
        $this->postPayment(600000.0, now()->subMonth()->format('Y-m-d'))->assertSessionHasNoErrors();

        $this->assertSame(2, LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)->count());
    }

    public function test_a_contract_of_a_different_leasing_company_is_refused(): void
    {
        $this->postPayment(100000.0, null, $this->otherLeasingCompany->id)
            ->assertSessionHasErrors('leasing_contract_id');

        $this->assertSame(0, LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)->count());
    }

    public function test_deleting_the_payment_gives_the_room_back(): void
    {
        $this->postPayment(400000.0)->assertSessionHasNoErrors();

        $payment = $this->lastPayment();

        $this->actingAs($this->actor)
            ->delete($this->url('money-payment/delete/'.$payment->id));

        $this->assertNull(MoneyPayment::find($payment->id), 'The payment must be gone.');
        $this->assertSame(
            0,
            LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)->count(),
            'Its drawdown must be gone with it.'
        );
        $this->assertEquals(self::LIMIT, $this->contract->fresh()->getAvailableRoomAt());
    }

    /* ── the list ──────────────────────────────────────────────────── */

    public function test_through_leasing_payments_are_listed_on_their_own_tab(): void
    {
        $this->postPayment(250000.0)->assertSessionHasNoErrors();

        $response = $this->actingAs($this->actor)->get($this->url('money-payment').'?active='.MoneyPayment::LEASING);

        $response->assertOk();

        $props = $this->inertiaProps($response);

        $this->assertArrayHasKey(MoneyPayment::LEASING, $props['tabs'], 'The tab must exist.');

        $rows = $props['tabs'][MoneyPayment::LEASING]['paginator']['data'];
        $row = collect($rows)->firstWhere('id', $this->lastPayment()->id);

        $this->assertNotNull($row, 'The payment must appear on its tab.');
        $this->assertSame($this->leasingCompany->getName(), $row['leasing_company_name']);
        $this->assertSame($this->contract->getName(), $row['leasing_contract_name']);
    }

    /* ── the repayment side ────────────────────────────────────────── */

    /**
     * Repaying an installment must give the principle half of it back to
     * the contract's room. Driven through the REAL settlement route, so
     * it also proves the controller actually calls the posting — a test
     * that called handleLeasingContractRepayment() itself would pass
     * even if nothing on the screen ever reached it.
     */
    public function test_repaying_an_installment_replenishes_the_room(): void
    {
        $this->postPayment(600000.0)->assertSessionHasNoErrors();

        $this->assertEquals(400000.0, $this->contract->fresh()->getAvailableRoomAt());

        $schedule = $this->makeInstallment(interest: 20000.0, principle: 80000.0);

        // Pay the whole installment: 20,000 interest + 80,000 principle.
        $this->actingAs($this->actor)
            ->post($this->url('contract-loan-schedule-settlements/'.$schedule->id), [
                'current_account_number' => $this->currentAccount->account_number,
                'amount' => 100000.0,
                'date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $row = LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)
            ->where('type', LeasingContractBankStatement::INSTALLMENT_REPAYMENT)
            ->first();

        $this->assertNotNull($row, 'The repayment must reach the contract ledger.');
        $this->assertEquals(80000.0, (float) $row->debit, 'Only the principle half moves the balance.');
        $this->assertEquals(20000.0, (float) $row->interest_amount, 'The interest half is recorded, not applied.');
        $this->assertEquals(0.0, (float) $row->credit);
        $this->assertEquals(-520000.0, (float) $row->end_balance, '-600,000 + 80,000.');

        $this->assertEquals(
            480000.0,
            $this->contract->fresh()->getAvailableRoomAt(),
            'The principle repaid is available to pay suppliers again; the interest is not.'
        );
    }

    /**
     * A contract CashVero never paid anything out of has no ledger at
     * all, so repaying its installments must not conjure one — those are
     * the contracts a company was already repaying before it joined.
     */
    public function test_repaying_a_contract_that_was_never_drawn_from_posts_nothing(): void
    {
        $schedule = $this->makeInstallment(interest: 20000.0, principle: 80000.0);

        $this->actingAs($this->actor)
            ->post($this->url('contract-loan-schedule-settlements/'.$schedule->id), [
                'current_account_number' => $this->currentAccount->account_number,
                'amount' => 100000.0,
                'date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            LeasingContractBankStatement::where('leasing_contract_id', $this->contract->id)->count()
        );
    }

    /* ── the contract statement ────────────────────────────────────── */

    public function test_the_contract_statement_shows_the_drawdown_and_the_room(): void
    {
        $this->postPayment(350000.0)->assertSessionHasNoErrors();

        $response = $this->actingAs($this->actor)->get($this->url(
            'leasing-companies/'.$this->leasingCompany->id.'/contracts/'.$this->contract->id.'/statement'
        ));

        $response->assertOk();

        $props = $this->inertiaProps($response);

        $this->assertSame($this->contract->getName(), $props['contract']['name']);
        $this->assertEquals(self::LIMIT, $props['contract']['limit']);
        $this->assertEquals(350000.0, $props['contract']['drawn']);
        $this->assertEquals(self::LIMIT - 350000.0, $props['contract']['available_room']);

        $this->assertArrayNotHasKey(
            'breakdown',
            $props,
            'The installment schedule was removed from this page — it duplicated the schedule screen.'
        );

        $this->assertCount(1, $props['ledger']);
        $this->assertEquals(350000.0, $props['ledger'][0]['credit']);
        $this->assertFalse($props['ledger'][0]['is_repayment']);
        $this->assertEquals(self::LIMIT - 350000.0, $props['ledger'][0]['room']);
    }

    /* ── the statement from the Statements sidebar ─────────────────── */

    public function test_the_sidebar_offers_the_leasing_contract_statement(): void
    {
        $menu = \App\Support\SidebarMenu::build($this->company, $this->actor);

        $item = collect($menu['statements']['items'] ?? [])
            ->firstWhere('title', 'Leasing Contract Statement');

        $this->assertNotNull($item, 'The Statements section must offer the leasing contract statement.');
        $this->assertTrue($item['show'], 'It must be visible to someone holding the permission.');
        $this->assertStringContainsString('leasing-contract-statement', $item['link']);
    }

    public function test_the_statement_picker_lists_every_contract_of_a_leasing_company(): void
    {
        $props = $this->inertiaProps(
            $this->actingAs($this->actor)->get($this->url('leasing-contract-statement'))
        );

        $this->assertContains(
            $this->leasingCompany->getName(),
            array_column($props['leasingCompanies'], 'name')
        );

        $currencies = $this->actingAs($this->actor)->getJson(
            $props['urls']['currencies'].'?'.http_build_query(['leasing_company_id' => $this->leasingCompany->id])
        );

        $currencies->assertOk();
        $this->assertArrayHasKey('egp', $currencies->json('currencies'));

        $response = $this->actingAs($this->actor)->getJson(
            $props['urls']['contracts'].'?'.http_build_query([
                'leasing_company_id' => $this->leasingCompany->id,
                'currency' => 'egp',
            ])
        );

        $response->assertOk();

        $this->assertContains(
            $this->contract->id,
            array_column($response->json('contracts'), 'id')
        );
    }

    /**
     * The contract list narrows to contracts whose own life overlaps the
     * chosen period — one that ended before the period began has nothing
     * to show inside it.
     */
    public function test_the_statement_picker_narrows_contracts_to_the_chosen_period(): void
    {
        $listed = fn (string $start, string $end) => array_column(
            $this->actingAs($this->actor)->getJson($this->url('leasing-contract-statement/contracts').'?'.http_build_query([
                'leasing_company_id' => $this->leasingCompany->id,
                'currency' => 'egp',
                'start_date' => $start,
                'end_date' => $end,
            ]))->json('contracts'),
            'id'
        );

        $this->assertContains(
            $this->contract->id,
            $listed(now()->subYear()->format('Y-m-d'), now()->addYears(5)->format('Y-m-d'))
        );

        // The contract starts 2 months ago and runs 3 years out, so a
        // window long finished cannot contain it.
        $this->assertNotContains(
            $this->contract->id,
            $listed('2015-01-01', '2015-12-31')
        );
    }

    /**
     * The period restricts which rows are LISTED. It must never restart
     * the balance, and the facility figures must be read as of the end
     * date rather than as of today.
     */
    public function test_the_period_filters_rows_without_resetting_the_balance(): void
    {
        $this->postPayment(250000.0, now()->subMonth()->format('Y-m-d'))->assertSessionHasNoErrors();

        $wholeLife = $this->statementFor(
            now()->subYears(5)->format('Y-m-d'),
            now()->addYears(5)->format('Y-m-d')
        );

        $this->assertCount(1, $wholeLife['ledger'], 'The drawdown is inside the wide window.');

        // A window that starts after the drawdown: no rows, but the
        // contract is still drawn down by it.
        $after = $this->statementFor(
            now()->addMonth()->format('Y-m-d'),
            now()->addYears(2)->format('Y-m-d')
        );

        $this->assertCount(0, $after['ledger'], 'No movement inside this window.');
        $this->assertEquals(
            self::LIMIT - 250000.0,
            $after['contract']['available_room'],
            'The room still reflects the earlier drawdown — a period is a window, not a reset.'
        );

        /**
         * The KPI row is what makes that visible on screen: the period
         * OPENS at the balance carried in from before it, exactly as
         * Bank Statement's does, rather than at zero.
         */
        $this->assertEquals(-250000.0, $after['kpis']['beginningBalance'], 'The period opens where the last movement left off.');
        $this->assertEquals(-250000.0, $after['kpis']['endingBalance'], 'Nothing moved inside it, so it closes there too.');
        $this->assertEquals(0.0, $after['kpis']['totalCredit']);
        $this->assertEquals(0.0, $after['kpis']['totalDebit']);
        $this->assertEquals(0, $after['kpis']['transactionCount']);

        // And the window containing the drawdown opens at zero and
        // closes at the drawn balance.
        $this->assertEquals(0.0, $wholeLife['kpis']['beginningBalance']);
        $this->assertEquals(-250000.0, $wholeLife['kpis']['endingBalance']);
        $this->assertEquals(250000.0, $wholeLife['kpis']['totalCredit'], 'Drawing is a credit.');
        $this->assertEquals(1, $wholeLife['kpis']['transactionCount']);

        // A window that ends BEFORE the drawdown: the contract had not
        // been drawn from yet, so the whole limit was available then.
        $before = $this->statementFor(
            now()->subYears(2)->format('Y-m-d'),
            now()->subMonths(2)->format('Y-m-d')
        );

        $this->assertCount(0, $before['ledger']);
        $this->assertEquals(
            self::LIMIT,
            $before['contract']['available_room'],
            'Read as of the end date, the contract was still untouched.'
        );
    }

    /**
     * The picker's list is NOT the payment card's list: a statement is a
     * record, so a contract that is finished — or in another currency —
     * must still be readable here even though it can no longer be paid
     * from.
     */
    public function test_the_statement_picker_also_lists_contracts_that_cannot_be_paid_from(): void
    {
        $closed = LeasingContract::create([
            'company_id' => $this->company->id,
            'leasing_company_id' => $this->leasingCompany->id,
            'status' => 'closed',
            'name' => 'TEST-CLOSED-'.bin2hex(random_bytes(4)),
            'start_date' => now()->subYears(3)->format('Y-m-d'),
            'end_date' => now()->subYear()->format('Y-m-d'),
            'currency' => 'egp',
            'limit' => 250000,
            'borrowing_rate' => 10,
            'margin_rate' => 2,
            'duration' => 24,
            'installment_payment_interval' => 'monthly',
        ]);

        try {
            $listed = array_column(
                $this->actingAs($this->actor)->getJson($this->url('leasing-contract-statement/contracts')
                    .'?'.http_build_query([
                        'leasing_company_id' => $this->leasingCompany->id,
                        'currency' => 'egp',
                    ]))->json('contracts'),
                'id'
            );

            $this->assertContains($closed->id, $listed, 'A closed contract still has a statement to read.');

            $payable = LeasingContract::payableFor($this->company->id, $this->leasingCompany->id, 'egp')
                ->pluck('id')
                ->all();

            $this->assertNotContains($closed->id, $payable, '…but it can no longer be paid from.');
        } finally {
            LeasingContract::where('id', $closed->id)->delete();
        }
    }

    /**
     * Both ways in must show the same numbers. They render the same page
     * from the same builder, and this is what keeps it that way.
     */
    public function test_both_routes_to_the_statement_agree(): void
    {
        $this->postPayment(120000.0)->assertSessionHasNoErrors();

        $fromList = $this->inertiaProps($this->actingAs($this->actor)->get($this->url(
            'leasing-companies/'.$this->leasingCompany->id.'/contracts/'.$this->contract->id.'/statement'
        )));

        // No dates on this one: with no period the sidebar route must
        // produce exactly what the contract list's button produces.
        $fromSidebar = $this->inertiaProps($this->actingAs($this->actor)->get(
            $this->url('leasing-contract-statement/result').'?'.http_build_query([
                'leasing_company_id' => $this->leasingCompany->id,
                'leasing_contract_id' => $this->contract->id,
            ])
        ));

        foreach (['contract', 'kpis', 'ledger'] as $key) {
            $this->assertEquals($fromList[$key], $fromSidebar[$key], "The two routes disagree about [{$key}].");
        }

        // Only the way back differs.
        $this->assertNotSame($fromList['backUrl'], $fromSidebar['backUrl']);
        $this->assertSame('Back to Leasing Contracts', $fromList['backLabel']);
        $this->assertSame('Back to Filters', $fromSidebar['backLabel']);
    }

    /**
     * Reading a contract under another company's URL must be refused,
     * not answered.
     *
     * Two guards stand behind this and either is enough: the company
     * scope refuses a user who does not belong to that company (403),
     * and the controller's own `where('company_id', ...)` refuses the
     * contract itself (404). The actor here is only in one company, so
     * the first one fires — asserting on the specific code would be
     * asserting on which guard happened to win.
     */
    public function test_the_result_route_refuses_a_contract_under_another_companys_url(): void
    {
        $otherCompanyId = Company::where('id', '!=', $this->company->id)->value('id');

        if (! $otherCompanyId) {
            $this->markTestSkipped('Development database has only one company.');
        }

        $response = $this->actingAs($this->actor)->get(
            '/en/'.$otherCompanyId.'/leasing-contract-statement/result?'.http_build_query([
                'leasing_company_id' => $this->leasingCompany->id,
                'leasing_contract_id' => $this->contract->id,
            ])
        );

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'A contract must never be readable under another company\'s URL.'
        );
    }

    /* ── helpers ───────────────────────────────────────────────────── */

    private function postPayment(float $amount, ?string $date = null, ?int $leasingCompanyId = null)
    {
        return $this->actingAs($this->actor)->post(
            $this->url('money-payment/create'),
            [
                'type' => MoneyPayment::LEASING,
                'partner_type' => 'is_other_partner',
                'supplier_id' => $this->partner->id,
                'delivery_date' => $date ?: now()->format('Y-m-d'),
                'currency' => 'egp',
                'payment_currency' => 'egp',
                'leasing_company_id' => $leasingCompanyId ?: $this->leasingCompany->id,
                'leasing_contract_id' => $this->contract->id,
                'paid_amount' => [MoneyPayment::LEASING => $amount],
                'exchange_rate' => [MoneyPayment::LEASING => 1],
            ]
        );
    }

    /**
     * One installment on this contract, plus the funded current account
     * the settlement screen draws from. Built lazily because only the
     * repayment tests need it.
     */
    private function makeInstallment(float $interest, float $principle, ?string $date = null): ContractLoanSchedule
    {
        if (! $this->bank) {
            $this->bank = FinancialInstitution::create([
                'company_id' => $this->company->id,
                'type' => 'bank',
                'name' => 'TEST Bank '.bin2hex(random_bytes(4)),
                'branch_name' => 'TEST Branch',
            ]);

            $this->currentAccount = FinancialInstitutionAccount::create([
                'company_id' => $this->company->id,
                'financial_institution_id' => $this->bank->id,
                'account_number' => 'TEST-'.bin2hex(random_bytes(4)),
                'currency' => 'egp',
                'is_active' => 1,
                'balance_amount' => 0,
                'balance_date' => now()->subYear()->format('Y-m-d'),
            ]);

            /**
             * Fund it, so validateCurrentAccountBalanceForSettlement()
             * sees real money rather than refusing the settlement.
             *
             * ⚠️ On a CURRENT account the sign convention is the opposite
             * of a credit facility's: debit is money coming IN. Crediting
             * here would seed an overdrawn account, and the settlement
             * would be refused for insufficient balance.
             */
            \App\Models\CurrentAccountBankStatement::create([
                'financial_institution_account_id' => $this->currentAccount->id,
                'company_id' => $this->company->id,
                'is_debit' => 1,
                'is_credit' => 0,
                'date' => now()->subMonths(6)->format('Y-m-d'),
                'debit' => 5000000,
                'credit' => 0,
                'beginning_balance' => 0,
            ]);
        }

        return ContractLoanSchedule::create([
            'leasing_contract_id' => $this->contract->id,
            'company_id' => $this->company->id,
            'date' => $date ?: now()->format('Y-m-d'),
            'beginning_balance' => self::LIMIT,
            'cheque_amount' => $interest + $principle,
            'interest_amount' => $interest,
            'principle_amount' => $principle,
            'end_balance' => self::LIMIT - $principle,
            'remaining' => $interest + $principle,
            'status' => 'not_paid',
            'drawee_bank_id' => $this->bank->id,
            'financial_institution_account_id' => $this->currentAccount->id,
            'account_number' => $this->currentAccount->account_number,
        ]);
    }

    /**
     * The statement props for a period, through the sidebar route.
     */
    private function statementFor(string $start, string $end): array
    {
        return $this->inertiaProps($this->actingAs($this->actor)->get(
            $this->url('leasing-contract-statement/result').'?'.http_build_query([
                'leasing_company_id' => $this->leasingCompany->id,
                'leasing_contract_id' => $this->contract->id,
                'start_date' => $start,
                'end_date' => $end,
            ])
        ));
    }

    private function lastPayment(): ?MoneyPayment
    {
        $id = DB::table('leasing_payments')
            ->where('leasing_contract_id', $this->contract->id)
            ->orderByDesc('id')
            ->value('money_payment_id');

        return $id ? MoneyPayment::find($id) : null;
    }

    /**
     * ⚠️ Every URL here is built WITH the /{locale} prefix on purpose.
     *
     * getCurrentCompanyId() — which StoreMoneyPaymentRequest and several
     * rules call — is `Request()->segment(2)`, so it only lands on the
     * company id while that prefix is present. route() omits it from the
     * CLI, which would silently hand the rules the string
     * "money-payment" as a company id. A real browser request always
     * carries the prefix, so this is what faithful coverage looks like.
     */
    private function url(string $path): string
    {
        return '/en/'.$this->company->id.'/'.ltrim($path, '/');
    }

    private function inertiaProps($response): array
    {
        return $response->viewData('page')['props'];
    }

    private function makeActor(): User
    {
        $user = new User;
        $user->name = 'Leasing Payment Test Actor';
        $user->email = 'leasing-payment-test-'.bin2hex(random_bytes(6)).'@example.test';
        $user->password = bcrypt('secret-'.bin2hex(random_bytes(8)));
        $user->save();

        $user->companies()->attach($this->company->id);

        $permissions = [];

        foreach ([
            'money_payment.view',
            'money_payment.create',
            'money_payment.update',
            'money_payment.delete',
            'leasing_contract.view',
            // The installment-settlement screen the repayment tests drive.
            'contract_loan_schedule.create',
            // The Statements sidebar route to the same statement.
            'report_leasing_contract_statement.view',
        ] as $key) {
            $permission = Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $this->createdPermissionIds[] = $permission->id;
            }

            $permissions[] = $permission;
        }

        $user->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        return $user->fresh();
    }
}

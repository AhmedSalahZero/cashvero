<?php

namespace Tests\Feature\Balances;

use App\Models\Company;
use App\Models\InternalSettlement;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Internal settlements — offsetting a partner who is both a customer
 * and a supplier against themselves.
 *
 * The thing worth testing here is the arithmetic and the ceiling: one
 * stored row has to move BOTH balances by the same amount and show on
 * BOTH statements, and it must never be allowed to move more than the
 * customer actually owes.
 *
 * @see \App\Models\InternalSettlement
 * @see \App\Http\Controllers\BalancesController::storeInternalSettlement()
 */
class InternalSettlementTest extends TestCase
{
    private const COMPANY = 700;

    /** Customer AND supplier — the only kind a settlement applies to. */
    private const DUAL = 70;

    /** Customer only — must never be offered a settlement. */
    private const CUSTOMER_ONLY = 71;

    /** @var list<string> */
    private array $tables = [
        'internal_settlements', 'down_payment_settlements', 'customer_invoices',
        'invoice_deductions', 'money_received', 'money_payments',
        'factoring_transactions', 'letter_of_credit_issuances', 'payment_settlements',
        'partners', 'companies',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();
        $this->createSchema();

        DB::table('companies')->insert(['id' => self::COMPANY, 'main_functional_currency' => 'EGP']);
        DB::table('partners')->insert([
            ['id' => self::DUAL, 'company_id' => self::COMPANY, 'name' => 'Ahmed', 'is_customer' => 1, 'is_supplier' => 1],
            ['id' => self::CUSTOMER_ONLY, 'company_id' => self::COMPANY, 'name' => 'Mona', 'is_customer' => 1, 'is_supplier' => 0],
        ]);

        // getCurrentCompanyId() reads segment 2 of the URL.
        $this->app->instance('request', Request::create('/en/'.self::COMPANY.'/customer-balances/CustomerInvoice', 'GET'));
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        Schema::create('companies', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('main_functional_currency')->nullable();
        });
        Schema::create('partners', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_supplier')->default(0);
        });
        Schema::create('customer_invoices', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('net_balance', 18, 5)->default(0);
            $table->decimal('net_balance_in_main_currency', 18, 5)->default(0);
        });
        Schema::create('down_payment_settlements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('down_payment_balance', 18, 5)->default(0);
        });

        /**
         * The statement builder walks several sources before it reaches
         * internal settlements. They stay EMPTY here — they only need
         * to exist so the queries run, which is what leaves the
         * settlement rows as the only thing in the result.
         */
        Schema::create('invoice_deductions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_type')->nullable();
            $table->date('date')->nullable();
        });
        foreach (['money_received', 'money_payments'] as $moneyTable) {
            Schema::create($moneyTable, function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->string('partner_type')->nullable();
                $table->date('receiving_date')->nullable();
                $table->date('delivery_date')->nullable();
            });
        }
        Schema::create('factoring_transactions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('recourse_type')->nullable();
            $table->date('factoring_date')->nullable();
        });
        Schema::create('letter_of_credit_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->date('payment_date')->nullable();
        });
        Schema::create('payment_settlements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('letter_of_Credit_issuance_id')->nullable();
        });

        require_once database_path('migrations/2026_08_25_100000_create_internal_settlements_table.php');
        (require database_path('migrations/2026_08_25_100000_create_internal_settlements_table.php'))->up();
    }

    private function invoice(float $netBalance, string $currency = 'EGP', int $partnerId = self::DUAL): void
    {
        DB::table('customer_invoices')->insert([
            'company_id' => self::COMPANY,
            'customer_id' => $partnerId,
            'currency' => $currency,
            'net_balance' => $netBalance,
            'net_balance_in_main_currency' => $netBalance,
        ]);
    }

    private function settle(float $amount, string $currency = 'EGP', int $partnerId = self::DUAL, ?float $mainAmount = null): InternalSettlement
    {
        return InternalSettlement::create([
            'company_id' => self::COMPANY,
            'partner_id' => $partnerId,
            'currency' => $currency,
            'settlement_date' => Carbon::today()->format('Y-m-d'),
            'amount' => $amount,
            'exchange_rate' => 1,
            'amount_in_main_currency' => $mainAmount ?? $amount,
        ]);
    }

    private function submitSettlement(array $payload)
    {
        $request = Request::create('/en/'.self::COMPANY.'/customer-balances/internal-settlement', 'POST', $payload);
        $this->app->instance('request', $request);

        return app(\App\Http\Controllers\BalancesController::class)
            ->storeInternalSettlement($request, Company::findOrFail(self::COMPANY));
    }

    // ---------------------------------------------------------------
    // the ceiling
    // ---------------------------------------------------------------

    public function test_the_amount_available_is_invoices_minus_down_payments_minus_what_is_already_settled(): void
    {
        $this->invoice(10000);
        DB::table('down_payment_settlements')->insert([
            'company_id' => self::COMPANY, 'customer_id' => self::DUAL,
            'currency' => 'EGP', 'down_payment_balance' => 1000,
        ]);
        $this->settle(2000);

        $available = $this->invokeAvailable('EGP');

        $this->assertSame(7000.0, $available, '10,000 invoices − 1,000 down payments − 2,000 already settled.');
    }

    public function test_a_settlement_within_the_balance_is_saved(): void
    {
        $this->invoice(10000);

        $this->submitSettlement([
            'partner_id' => self::DUAL, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'), 'amount' => 5000,
        ]);

        $this->assertSame(5000.0, InternalSettlement::totalFor(self::COMPANY, self::DUAL, 'EGP'));
        $this->assertSame(5000.0, $this->invokeAvailable('EGP'), 'What is left to settle drops by what was settled.');
    }

    public function test_a_settlement_above_the_customer_balance_is_refused(): void
    {
        $this->invoice(10000);

        $response = $this->submitSettlement([
            'partner_id' => self::DUAL, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'), 'amount' => 10001,
        ]);

        $this->assertSame(0.0, InternalSettlement::totalFor(self::COMPANY, self::DUAL, 'EGP'));
        $this->assertNotNull($response->getSession()->get('fail'));
    }

    /**
     * The ceiling has to count settlements already made, not just the
     * invoice total — otherwise the same 10,000 could be handed over
     * twice by opening the page twice.
     */
    public function test_earlier_settlements_count_against_the_ceiling(): void
    {
        $this->invoice(10000);
        $this->settle(6000);

        $this->submitSettlement([
            'partner_id' => self::DUAL, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'), 'amount' => 5000,
        ]);

        $this->assertSame(6000.0, InternalSettlement::totalFor(self::COMPANY, self::DUAL, 'EGP'),
            '4,000 was all that remained, so the second 5,000 must not have been taken.');
    }

    public function test_a_partner_who_is_not_also_a_supplier_cannot_be_settled(): void
    {
        $this->invoice(10000, 'EGP', self::CUSTOMER_ONLY);

        $response = $this->submitSettlement([
            'partner_id' => self::CUSTOMER_ONLY, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'), 'amount' => 100,
        ]);

        $this->assertSame(0.0, InternalSettlement::totalFor(self::COMPANY, self::CUSTOMER_ONLY, 'EGP'));
        $this->assertNotNull($response->getSession()->get('fail'));
    }

    /**
     * main_currency is a roll-up across every currency the partner is
     * owed in, not a balance anyone holds — an offset booked against it
     * would have no single rate to be unwound at.
     */
    public function test_the_main_currency_roll_up_cannot_be_settled(): void
    {
        $this->invoice(10000);

        $response = $this->submitSettlement([
            'partner_id' => self::DUAL, 'currency' => 'main_currency',
            'settlement_date' => Carbon::today()->format('Y-m-d'), 'amount' => 100,
        ]);

        $this->assertSame(0, InternalSettlement::query()->count());
        $this->assertNotNull($response->getSession()->get('fail'));
    }

    // ---------------------------------------------------------------
    // the balances page
    // ---------------------------------------------------------------

    public function test_a_settlement_comes_off_the_net_balance_and_is_shown_as_its_own_column(): void
    {
        $rows = [
            (object) ['customer_id' => self::DUAL, 'currency' => 'EGP', 'net_balance' => 10000],
        ];

        $this->invokeApply($rows, [self::DUAL.'|EGP' => 4000.0]);

        $this->assertSame(6000.0, (float) $rows[0]->net_balance, '10,000 − 4,000 settled.');
        $this->assertSame(4000.0, (float) $rows[0]->internal_settlement_amount);
    }

    public function test_a_row_with_no_settlement_is_left_exactly_as_it_was(): void
    {
        $rows = [
            (object) ['customer_id' => self::CUSTOMER_ONLY, 'currency' => 'EGP', 'net_balance' => 900],
        ];

        $this->invokeApply($rows, [self::DUAL.'|EGP' => 4000.0]);

        $this->assertSame(900.0, (float) $rows[0]->net_balance);
        $this->assertSame(0, $rows[0]->internal_settlement_amount);
    }

    /**
     * The main-currency row stands for every currency at once, so its
     * figure is the SUM of each currency's converted amount — not
     * whichever currency happened to be read last.
     */
    public function test_the_main_currency_total_adds_up_every_currency(): void
    {
        $this->settle(1000, 'EGP', self::DUAL, 1000);
        $this->settle(100, 'USD', self::DUAL, 5000);

        $totals = InternalSettlement::totalsByPartnerAndCurrency(self::COMPANY, [self::DUAL]);

        $this->assertSame(1000.0, $totals[self::DUAL.'|EGP']);
        $this->assertSame(100.0, $totals[self::DUAL.'|USD']);
        $this->assertSame(6000.0, $totals[self::DUAL.'|main_currency'], '1,000 EGP + 100 USD at 50 = 6,000.');
    }

    // ---------------------------------------------------------------
    // both statements
    // ---------------------------------------------------------------

    /**
     * On the CUSTOMER's statement the settlement is a credit: they owe
     * that much less, exactly as if they had paid it.
     */
    public function test_the_customer_statement_shows_the_settlement_as_a_credit(): void
    {
        $this->settle(5000);

        $row = $this->statementRow('CustomerInvoice');

        $this->assertSame(0, $row['debit']);
        $this->assertSame(5000.0, $row['credit']);
        $this->assertSame(__('Internal Settlement'), $row['document_type']);
        $this->assertStringContainsString('Internal Settlement', $row['comment']);
    }

    /**
     * On the SUPPLIER's statement the same row is a debit: we owe them
     * that much less, exactly as if we had paid it.
     */
    public function test_the_supplier_statement_shows_the_settlement_as_a_debit(): void
    {
        $this->settle(5000);

        $row = $this->statementRow('SupplierInvoice');

        $this->assertSame(5000.0, $row['debit']);
        $this->assertSame(0, $row['credit']);
        $this->assertSame(__('Internal Settlement'), $row['document_type']);
    }

    /** The user's own note rides along with the standard explanation. */
    public function test_the_users_comment_is_kept_next_to_the_row(): void
    {
        $settlement = $this->settle(5000);
        $settlement->update(['user_comment' => 'Agreed with Ahmed on the phone']);

        $this->assertStringContainsString(
            'Agreed with Ahmed on the phone',
            $this->statementRow('CustomerInvoice')['comment']
        );
    }

    public function test_a_settlement_outside_the_statement_period_is_not_shown(): void
    {
        $settlement = $this->settle(5000);
        $settlement->update(['settlement_date' => Carbon::today()->subYears(3)->format('Y-m-d')]);

        $this->assertSame([], $this->statementRows('CustomerInvoice'));
    }

    public function test_a_settlement_in_another_currency_is_not_shown(): void
    {
        $this->settle(5000, 'USD');

        $this->assertSame([], $this->statementRows('CustomerInvoice'), 'The statement is in EGP.');
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function invokeAvailable(string $currency): float
    {
        $controller = app(\App\Http\Controllers\BalancesController::class);
        $method = new \ReflectionMethod($controller, 'customerBalanceAvailableToSettle');
        $method->setAccessible(true);

        return $method->invoke($controller, Company::findOrFail(self::COMPANY), self::DUAL, $currency);
    }

    private function invokeApply(array $rows, array $totals): void
    {
        $controller = app(\App\Http\Controllers\BalancesController::class);
        $method = new \ReflectionMethod($controller, 'applyInternalSettlements');
        $method->setAccessible(true);
        $method->invoke($controller, $rows, $totals, 'customer_id');
    }

    /**
     * The statement rows produced for one side, with every other source
     * (invoices, deductions, money, factoring, LC) left empty — so what
     * comes back is the settlement's own contribution and nothing else.
     *
     * @return list<array<string, mixed>>
     */
    private function statementRows(string $modelType): array
    {
        $formatted = [];
        $index = 0;

        \App\Http\Controllers\CustomerInvoiceDashboardController::appendBalances(
            false,
            'EGP',
            collect(),
            $index,
            $formatted,
            self::DUAL,
            Carbon::today()->subYear()->format('Y-m-d'),
            Carbon::today()->format('Y-m-d'),
            [],
            $modelType,
            true
        );

        return array_values($formatted);
    }

    /** @return array<string, mixed> */
    private function statementRow(string $modelType): array
    {
        $rows = $this->statementRows($modelType);
        $this->assertCount(1, $rows, 'Exactly one settlement row was expected.');

        return $rows[0];
    }
}

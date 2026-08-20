<?php

namespace Tests\Feature\Deletion;

use App\Support\Deletion\ReferencedRecordGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * How the guard decides, on a schema small enough to reason about.
 *
 * DeletionDependentsTest covers the other half — that the declared list
 * still matches the real database.
 *
 * @see \App\Support\Deletion\ReferencedRecordGuard
 */
class ReferencedRecordGuardTest extends TestCase
{
    /**
     * Deliberately all different. With bank 1 / account 1 a lookup that
     * skipped the bridge entirely still matched by accident, and the
     * "movement on one of its accounts" rule could be deleted without a
     * single test noticing.
     */
    private const BANK = 1;
    private const ACCOUNT = 77;
    private const PARTNER = 5;
    private const CONTRACT = 9;

    /** @var list<string> */
    private array $tables = [
        'down_payment_settlements',
        'sales_orders',
        'current_account_bank_statements',
        'account_interests',
        'money_payments',
        'customer_invoices',
        'internal_money_transfers',
        'letter_of_guarantee_issuances',
        'financial_institution_accounts',
        'financial_institutions',
        'contracts',
        'partners',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();

        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
        });
        Schema::create('contracts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('financial_institutions', function ($table) {
            $table->bigIncrements('id');
        });
        Schema::create('financial_institution_accounts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('financial_institution_id')->nullable();
            $table->unsignedBigInteger('shareholder_partner_id')->nullable();
        });
        Schema::create('account_interests', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('financial_institution_account_id')->nullable();
        });
        Schema::create('current_account_bank_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('financial_institution_account_id')->nullable();
            $table->boolean('is_beginning_balance')->default(0);
            $table->decimal('debit', 14)->default(0);
            $table->decimal('credit', 14)->default(0);
        });
        Schema::create('money_payments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
        });
        Schema::create('customer_invoices', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->nullable();
        });
        Schema::create('internal_money_transfers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('from_bank_id')->nullable();
            $table->unsignedBigInteger('to_bank_id')->nullable();
        });
        Schema::create('sales_orders', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contract_id')->nullable();
        });
        Schema::create('down_payment_settlements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable();
        });
        Schema::create('letter_of_guarantee_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('financial_institution_id')->nullable();
            $table->unsignedBigInteger('cash_cover_deducted_from_account_id')->nullable();
            $table->unsignedBigInteger('lg_fees_and_commission_account_id')->nullable();
        });

        DB::table('partners')->insert(['id' => self::PARTNER, 'name' => 'sce zone']);
        DB::table('contracts')->insert(['id' => self::CONTRACT, 'partner_id' => 99, 'name' => 'Contract A']);
        DB::table('financial_institutions')->insert(['id' => self::BANK]);
        DB::table('financial_institution_accounts')->insert([
            'id' => self::ACCOUNT, 'financial_institution_id' => self::BANK,
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }

    private function statement(array $attributes = []): void
    {
        DB::table('current_account_bank_statements')->insert($attributes + [
            'financial_institution_account_id' => self::ACCOUNT,
            'is_beginning_balance' => 0,
            'debit' => 0,
            'credit' => 500,
        ]);
    }

    // ---------------------------------------------------------------
    // nothing attached
    // ---------------------------------------------------------------

    public function test_a_record_with_nothing_attached_is_deletable(): void
    {
        foreach (ReferencedRecordGuard::guardedTables() as $parent) {
            $this->assertFalse(ReferencedRecordGuard::blocks($parent, 1), "{$parent} blocked with nothing attached.");
            $this->assertNull(ReferencedRecordGuard::blockMessage($parent, 1, 'x'));
        }
    }

    // ---------------------------------------------------------------
    // partners
    // ---------------------------------------------------------------

    public function test_a_partner_with_an_invoice_is_blocked(): void
    {
        DB::table('customer_invoices')->insert(['customer_id' => self::PARTNER]);

        $this->assertTrue(ReferencedRecordGuard::blocks('partners', self::PARTNER));
        $this->assertSame(['Customer Invoices' => 1], ReferencedRecordGuard::blockers('partners', self::PARTNER));
    }

    public function test_a_partner_with_a_money_payment_is_blocked(): void
    {
        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);

        $this->assertSame(['Money Payments' => 1], ReferencedRecordGuard::blockers('partners', self::PARTNER));
    }

    public function test_a_partner_with_a_contract_is_blocked(): void
    {
        DB::table('contracts')->insert(['id' => 2, 'partner_id' => self::PARTNER, 'name' => 'Theirs']);

        $this->assertSame(['Contracts' => 1], ReferencedRecordGuard::blockers('partners', self::PARTNER));
    }

    public function test_a_partner_who_owns_a_shareholder_bank_account_is_blocked(): void
    {
        DB::table('financial_institution_accounts')->insert([
            'id' => 2, 'financial_institution_id' => self::BANK, 'shareholder_partner_id' => self::PARTNER,
        ]);

        $this->assertSame(['Bank Accounts' => 1], ReferencedRecordGuard::blockers('partners', self::PARTNER));
    }

    public function test_someone_elses_records_do_not_block(): void
    {
        DB::table('partners')->insert(['id' => 2, 'name' => 'Someone else']);
        DB::table('money_payments')->insert(['partner_id' => 2]);
        DB::table('customer_invoices')->insert(['customer_id' => 2]);

        $this->assertFalse(ReferencedRecordGuard::blocks('partners', self::PARTNER));
    }

    // ---------------------------------------------------------------
    // contracts
    // ---------------------------------------------------------------

    public function test_a_contract_with_a_letter_of_guarantee_is_blocked(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert(['contract_id' => self::CONTRACT]);

        $this->assertSame(['Letters of Guarantee' => 1], ReferencedRecordGuard::blockers('contracts', self::CONTRACT));
    }

    public function test_a_contract_with_a_money_payment_is_blocked(): void
    {
        DB::table('money_payments')->insert(['contract_id' => self::CONTRACT]);

        $this->assertSame(['Money Payments' => 1], ReferencedRecordGuard::blockers('contracts', self::CONTRACT));
    }

    /**
     * A contract's own orders are part of the contract form and go with
     * it. Blocking on them would have made 94 of the 120 contracts on
     * record permanently undeletable.
     */
    public function test_a_contract_whose_only_child_is_its_own_order_stays_deletable(): void
    {
        DB::table('sales_orders')->insert(['id' => 40, 'contract_id' => self::CONTRACT]);

        $this->assertFalse(ReferencedRecordGuard::blocks('contracts', self::CONTRACT));
    }

    /**
     * ...but something hanging off that order is a real transaction,
     * and MySQL would cascade the order away underneath it.
     */
    public function test_a_contract_is_blocked_by_a_settlement_on_its_order(): void
    {
        DB::table('sales_orders')->insert(['id' => 40, 'contract_id' => self::CONTRACT]);
        DB::table('down_payment_settlements')->insert(['sales_order_id' => 40]);

        $this->assertSame(
            ['Down Payment Settlements' => 1],
            ReferencedRecordGuard::blockers('contracts', self::CONTRACT)
        );
    }

    public function test_a_settlement_on_another_contracts_order_does_not_block(): void
    {
        DB::table('contracts')->insert(['id' => 2, 'name' => 'Theirs']);
        DB::table('sales_orders')->insert(['id' => 41, 'contract_id' => 2]);
        DB::table('down_payment_settlements')->insert(['sales_order_id' => 41]);

        $this->assertFalse(ReferencedRecordGuard::blocks('contracts', self::CONTRACT));
        $this->assertTrue(ReferencedRecordGuard::blocks('contracts', 2));
    }

    // ---------------------------------------------------------------
    // banks — the "movement, not just existence" rule
    // ---------------------------------------------------------------

    /**
     * FinancialInstitutionController::destroy() deletes an empty bank's
     * accounts along with it, so an account on its own must not block —
     * otherwise no bank could ever be deleted.
     */
    public function test_a_bank_whose_only_account_is_empty_stays_deletable(): void
    {
        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institutions', self::BANK));
    }

    public function test_a_bank_is_blocked_by_movement_on_one_of_its_accounts(): void
    {
        $this->statement(['credit' => 4300.22]);

        $this->assertSame(
            ['Bank Statement Transactions' => 1],
            ReferencedRecordGuard::blockers('financial_institutions', self::BANK)
        );
    }

    public function test_an_opening_balance_alone_is_not_movement(): void
    {
        $this->statement(['is_beginning_balance' => 1, 'debit' => 64450.61, 'credit' => 0]);

        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institutions', self::BANK));
        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institution_accounts', self::ACCOUNT));
    }

    /**
     * The empty month-end interest placeholders the system writes for
     * itself are zero on both sides and must not count as movement.
     */
    public function test_a_zero_amount_system_row_is_not_movement(): void
    {
        $this->statement(['debit' => 0, 'credit' => 0]);

        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institutions', self::BANK));
    }

    public function test_movement_on_another_banks_account_does_not_block(): void
    {
        DB::table('financial_institutions')->insert(['id' => 2]);
        DB::table('financial_institution_accounts')->insert(['id' => 88, 'financial_institution_id' => 2]);
        $this->statement(['financial_institution_account_id' => 88, 'credit' => 900]);

        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institutions', self::BANK));
        $this->assertTrue(ReferencedRecordGuard::blocks('financial_institutions', 2));
    }

    public function test_a_bank_is_blocked_by_a_transfer_on_either_side(): void
    {
        DB::table('internal_money_transfers')->insert(['from_bank_id' => self::BANK]);
        DB::table('internal_money_transfers')->insert(['to_bank_id' => self::BANK]);

        // from_bank_id and to_bank_id share a label — they read as one number.
        $this->assertSame(
            ['Internal Money Transfers' => 2],
            ReferencedRecordGuard::blockers('financial_institutions', self::BANK)
        );
    }

    // ---------------------------------------------------------------
    // bank accounts
    // ---------------------------------------------------------------

    public function test_an_account_is_blocked_by_its_own_movement(): void
    {
        $this->statement(['credit' => 12.5]);

        $this->assertTrue(ReferencedRecordGuard::blocks('financial_institution_accounts', self::ACCOUNT));
    }

    public function test_an_account_is_blocked_when_an_lg_draws_fees_from_it(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert(['lg_fees_and_commission_account_id' => self::ACCOUNT]);

        $this->assertSame(
            ['Letter of Guarantee Fees & Commission' => 1],
            ReferencedRecordGuard::blockers('financial_institution_accounts', self::ACCOUNT)
        );
    }

    /**
     * Interest rates are the account's own configuration and are
     * deleted with it — they must not keep it alive.
     */
    public function test_interest_rates_alone_do_not_block_an_account(): void
    {
        DB::table('account_interests')->insert(['financial_institution_account_id' => self::ACCOUNT]);

        $this->assertFalse(ReferencedRecordGuard::blocks('financial_institution_accounts', self::ACCOUNT));
    }

    // ---------------------------------------------------------------
    // the message
    // ---------------------------------------------------------------

    public function test_the_message_names_the_record_and_the_biggest_blockers_first(): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('customer_invoices')->insert(['customer_id' => self::PARTNER]);
        }
        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);

        $message = ReferencedRecordGuard::blockMessage('partners', self::PARTNER, 'sce zone');

        $this->assertStringContainsString('sce zone', $message);
        $this->assertStringContainsString('5 Customer Invoices', $message);
        $this->assertStringContainsString('1 Money Payments', $message);
        $this->assertLessThan(
            strpos($message, '1 Money Payments'),
            strpos($message, '5 Customer Invoices'),
            'The biggest blocker should be named first.'
        );
    }

    public function test_the_message_summarises_once_there_are_too_many_kinds(): void
    {
        DB::table('customer_invoices')->insert(['customer_id' => self::PARTNER]);
        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);
        DB::table('contracts')->insert(['id' => 2, 'partner_id' => self::PARTNER]);
        DB::table('letter_of_guarantee_issuances')->insert(['partner_id' => self::PARTNER]);
        DB::table('financial_institution_accounts')->insert([
            'id' => 3, 'financial_institution_id' => self::BANK, 'shareholder_partner_id' => self::PARTNER,
        ]);

        $message = ReferencedRecordGuard::blockMessage('partners', self::PARTNER, 'sce zone');

        $this->assertStringContainsString('2 more', $message, 'Five kinds of blocker, three named, two summarised.');
    }

    public function test_the_message_falls_back_when_the_record_has_no_name(): void
    {
        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);

        $this->assertStringContainsString('this item',
            ReferencedRecordGuard::blockMessage('partners', self::PARTNER, null));
    }

    public function test_the_message_is_translated(): void
    {
        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);

        $this->app->setLocale('ar');
        $message = ReferencedRecordGuard::blockMessage('partners', self::PARTNER, 'sce zone');

        $this->assertStringContainsString('مينفعش تحذف', $message);
        $this->assertStringContainsString('المدفوعات', $message);
        $this->assertStringNotContainsString('Money Payments', $message);
    }

    /**
     * A dependent table this installation does not have must be skipped,
     * not blow the delete up with an SQL error.
     */
    public function test_a_missing_dependent_table_is_skipped_not_fatal(): void
    {
        Schema::dropIfExists('customer_invoices');

        DB::table('money_payments')->insert(['partner_id' => self::PARTNER]);

        $this->assertSame(['Money Payments' => 1], ReferencedRecordGuard::blockers('partners', self::PARTNER));
    }
}

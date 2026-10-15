<?php

namespace Tests\Feature\CashExpense;

use App\Http\Controllers\CashExpenseController;
use App\Models\CashExpense;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Copy button on the Cash Expense list.
 *
 * A copy is the CREATE form arriving pre-filled — not an edit. The two
 * things that matter are therefore: it saves as a new row rather than
 * over the one it came from, and it opens ready to save, without a
 * field in it that validation is guaranteed to reject.
 *
 * @see \App\Http\Controllers\CashExpenseController::copy()
 */
class CashExpenseCopyTest extends TestCase
{
    private const COMPANY = 800;

    private const CATEGORY = 810;

    private const CATEGORY_NAME = 811;

    private const BANK = 820;

    private const ACCOUNT_TYPE = 830;

    private const CUSTOMER = 840;

    private const CONTRACT = 850;

    /** @var list<string> */
    private array $tables = [
        'cash_expense_contract', 'payable_cheques', 'outgoing_transfers', 'cash_payments',
        'cash_expenses', 'cash_expense_category_names', 'cash_expense_categories',
        'contracts', 'partners', 'financial_institutions', 'banks', 'branch',
        'account_types', 'companies',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();
        $this->createSchema();
        $this->seedLookups();
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
        Schema::create('account_types', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('slug')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('branch', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('banks', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
        });
        Schema::create('financial_institutions', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('type')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('partners', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_supplier')->default(0);
        });
        Schema::create('contracts', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('model_type')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->decimal('amount', 18, 5)->default(0);
            $table->string('currency')->nullable();
        });
        Schema::create('cash_expense_categories', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('cash_expense_category_names', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('cash_expense_category_id')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('cash_expenses', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedBigInteger('cash_expense_category_name_id')->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('paid_amount', 18, 5)->default(0);
            $table->text('user_comment')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_payments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('delivery_branch_id')->nullable();
            $table->string('receipt_number')->nullable();
        });
        Schema::create('outgoing_transfers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('delivery_bank_id')->nullable();
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->string('account_number')->nullable();
            $table->boolean('is_bank_charges')->default(0);
        });
        Schema::create('payable_cheques', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('delivery_bank_id')->nullable();
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->string('account_number')->nullable();
            $table->date('due_date')->nullable();
            $table->string('cheque_number')->nullable();
        });
        Schema::create('cash_expense_contract', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->decimal('amount', 18, 5)->default(0);
            $table->timestamps();
        });
    }

    private function seedLookups(): void
    {
        DB::table('companies')->insert(['id' => self::COMPANY, 'main_functional_currency' => 'EGP']);
        DB::table('account_types')->insert(['id' => self::ACCOUNT_TYPE, 'slug' => 'current-account', 'name' => 'Current Account']);
        DB::table('financial_institutions')->insert([
            'id' => self::BANK, 'company_id' => self::COMPANY, 'bank_id' => null, 'type' => 'bank', 'name' => 'Test Bank',
        ]);
        DB::table('partners')->insert([
            'id' => self::CUSTOMER, 'company_id' => self::COMPANY, 'name' => 'A Customer', 'is_customer' => 1,
        ]);
        DB::table('contracts')->insert([
            'id' => self::CONTRACT, 'company_id' => self::COMPANY, 'partner_id' => self::CUSTOMER,
            'model_type' => 'Customer', 'code' => 'C-1', 'name' => 'Contract 1', 'amount' => 100000, 'currency' => 'EGP',
        ]);
        DB::table('cash_expense_categories')->insert(['id' => self::CATEGORY, 'company_id' => self::COMPANY, 'name' => 'Rent']);
        DB::table('cash_expense_category_names')->insert([
            'id' => self::CATEGORY_NAME, 'cash_expense_category_id' => self::CATEGORY, 'name' => 'Office Rent',
        ]);
    }

    /**
     * A payable cheque — the type that carries BOTH kinds of field a
     * copy has to be careful with: a unique cheque number, and its own
     * sub-record id.
     */
    private function payableCheque(): CashExpense
    {
        $expenseId = DB::table('cash_expenses')->insertGetId([
            'company_id' => self::COMPANY,
            'type' => CashExpense::PAYABLE_CHEQUE,
            'payment_date' => Carbon::today()->subMonth()->format('Y-m-d'),
            'currency' => 'EGP',
            'cash_expense_category_name_id' => self::CATEGORY_NAME,
            'exchange_rate' => 1,
            'paid_amount' => 7500,
            'user_comment' => 'March rent',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('payable_cheques')->insert([
            'cash_expense_id' => $expenseId,
            'delivery_bank_id' => self::BANK,
            'account_type_id' => self::ACCOUNT_TYPE,
            'account_number' => '1234567',
            'due_date' => Carbon::today()->addMonth()->format('Y-m-d'),
            'cheque_number' => 'CHQ-001',
        ]);

        return CashExpense::findOrFail($expenseId);
    }

    /** @return array<string, mixed> */
    private function props(CashExpense $expense, string $action): array
    {
        $request = Request::create('/en/'.self::COMPANY.'/cash-expense/'.$action.'/'.$expense->id, 'GET');
        $this->app->instance('request', $request);

        $company = Company::findOrFail(self::COMPANY);
        $controller = app(CashExpenseController::class);

        $response = $action === 'copy'
            ? $controller->copy($company, $expense)
            : $controller->edit($company, $request, $expense);

        $property = new \ReflectionProperty($response, 'props');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    // ---------------------------------------------------------------

    public function test_copy_opens_the_create_form_not_the_edit_form(): void
    {
        $props = $this->props($this->payableCheque(), 'copy');

        $this->assertSame('create', $props['mode']);
        $this->assertTrue($props['isCopy']);
        $this->assertStringContainsString('/cash-expense/create', $props['submitUrl'],
            'A copy must save as a NEW expense, not over the one it was copied from.');
    }

    public function test_the_form_arrives_filled_in_with_the_copied_expense(): void
    {
        $expense = $this->payableCheque();
        $props = $this->props($expense, 'copy');

        $this->assertSame(CashExpense::PAYABLE_CHEQUE, $props['model']['type']);
        $this->assertSame('EGP', $props['model']['currency']);
        $this->assertSame(self::CATEGORY_NAME, (int) $props['model']['cash_expense_category_name_id']);
        $this->assertSame(7500.0, (float) $props['model']['paid_amount']);
        $this->assertSame('March rent', $props['model']['user_comment']);
        $this->assertSame(self::BANK, (int) $props['model']['payable_cheque_delivery_bank_id']);
        $this->assertSame('1234567', $props['model']['payable_cheque_account_number']);
    }

    /**
     * The date starts empty on a copy, so the new expense is dated the
     * day it is actually being made rather than inheriting the copied
     * one. The form falls back to today when it is empty.
     */
    public function test_the_payment_date_starts_empty_on_a_copy(): void
    {
        $props = $this->props($this->payableCheque(), 'copy');

        $this->assertNull($props['model']['payment_date']);
    }

    /**
     * ...and EDIT must still open on the expense's real date.
     * buildFormProps() serves both, so blanking the date in the shared
     * array instead of in the copy-only list silently re-dates every
     * expense that gets edited and saved.
     */
    public function test_editing_still_opens_on_the_expenses_own_date(): void
    {
        $expense = $this->payableCheque();
        $props = $this->props($expense, 'edit');

        $this->assertSame($expense->getPaymentDate(), $props['model']['payment_date']);
        $this->assertNotNull($props['model']['payment_date']);
    }

    /**
     * The cheque number is unique per delivery bank. Copying it would
     * mean the very first Save is rejected — the opposite of what a
     * copy is for.
     */
    public function test_the_unique_numbers_are_not_copied(): void
    {
        $props = $this->props($this->payableCheque(), 'copy');

        $this->assertNull($props['model']['cheque_number']);
        $this->assertNull($props['model']['receipt_number']);
    }

    /**
     * The identity of the copied row must not ride along: those ids are
     * what the uniqueness rules exclude from their own check, so a copy
     * carrying them could slip a genuine duplicate past validation.
     */
    public function test_the_copied_rows_identity_is_not_carried_over(): void
    {
        $props = $this->props($this->payableCheque(), 'copy');

        $this->assertNull($props['model']['id']);
        $this->assertNull($props['model']['payable_cheque_id']);
        $this->assertNull($props['model']['cash_payment_id']);
    }

    public function test_the_contract_allocations_are_copied_too(): void
    {
        $expense = $this->payableCheque();
        DB::table('cash_expense_contract')->insert([
            'cash_expense_id' => $expense->id, 'contract_id' => self::CONTRACT,
            'amount' => 2500, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $props = $this->props(CashExpense::findOrFail($expense->id), 'copy');

        $this->assertCount(1, $props['existingAllocations']);
        $this->assertSame(self::CONTRACT, (int) $props['existingAllocations'][0]['contract_id']);
        $this->assertSame(2500.0, (float) $props['existingAllocations'][0]['amount']);
    }

    /**
     * Copying must leave the original completely alone — it is a read
     * that happens to render a form.
     */
    public function test_copying_changes_nothing_about_the_original(): void
    {
        $expense = $this->payableCheque();
        $before = DB::table('cash_expenses')->where('id', $expense->id)->first();
        $chequeBefore = DB::table('payable_cheques')->where('cash_expense_id', $expense->id)->first();

        $this->props($expense, 'copy');

        $this->assertEquals($before, DB::table('cash_expenses')->where('id', $expense->id)->first());
        $this->assertEquals($chequeBefore, DB::table('payable_cheques')->where('cash_expense_id', $expense->id)->first());
        $this->assertSame(1, DB::table('cash_expenses')->count(), 'Opening a copy must not insert anything.');
    }

    /**
     * Edit is untouched by any of this — it still points at update()
     * and still carries the ids and numbers its uniqueness checks need.
     */
    public function test_edit_still_behaves_exactly_as_before(): void
    {
        $expense = $this->payableCheque();
        $props = $this->props($expense, 'edit');

        $this->assertSame('edit', $props['mode']);
        $this->assertFalse($props['isCopy']);
        $this->assertStringContainsString('/cash-expense/update/'.$expense->id, $props['submitUrl']);
        $this->assertSame($expense->id, $props['model']['id']);
        $this->assertSame('CHQ-001', $props['model']['cheque_number']);
        $this->assertNotNull($props['model']['payable_cheque_id']);
    }
}

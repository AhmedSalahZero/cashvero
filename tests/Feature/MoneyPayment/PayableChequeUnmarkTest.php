<?php

namespace Tests\Feature\MoneyPayment;

use App\Models\AccountType;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\MoneyPayment;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Returning a paid payable cheque to unpaid must restore the due-date
 * statement movement. Runs against SMOKE_DB / cash-vero inside a
 * rolled-back transaction.
 */
class PayableChequeUnmarkTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    private FinancialInstitutionAccount $account;

    private User $user;

    private string $currency = 'EGP';

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
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
            $this->markTestSkipped('Development database has no financial institution.');
        }

        $this->bank = $bank;
        $this->company = Company::findOrFail($bank->company_id);

        $this->account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-UNMARK-'.uniqid(),
            'currency' => $this->currency,
            'balance_amount' => 0,
            'balance_date' => now()->subYear()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        DB::table('current_account_bank_statements')->insert([
            'financial_institution_account_id' => $this->account->id,
            'company_id' => $this->company->id,
            'date' => now()->subMonth()->format('Y-m-d'),
            'full_date' => now()->subMonth()->format('Y-m-d H:i:s'),
            'is_beginning_balance' => 1,
            'beginning_balance' => 0,
            'debit' => 1000,
            'end_balance' => 1000,
        ]);

        $this->user = User::create([
            'name' => 'Payable Cheque Unmark '.uniqid(),
            'email' => 'payable-unmark-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $this->user->givePermissionTo([
            'money_payment.view',
            'money_payment.mark_as_paid',
            'cash_expense.view',
            'cash_expense.mark_as_paid',
        ]);
        $this->user->companies()->attach($this->company->id);
        $this->user->load('companies');
        $this->assertFalse($this->user->isSuperAdmin());
    }

    public function test_money_payment_unmark_restores_pending_status_and_due_date_statement(): void
    {
        $dueDate = now()->subDays(10)->format('Y-m-d');
        $paymentDate = now()->subDays(5)->format('Y-m-d');
        $amount = 100.0;

        $moneyPayment = $this->makePaidMoneyPaymentCheque($dueDate, $paymentDate, $amount);

        $response = $this->actingAs($this->user)
            ->post(route('payable.cheque.unmark.as.paid', ['company' => $this->company->id]), [
                'cheques' => [$moneyPayment->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('view.money.payment', [
            'company' => $this->company->id,
            'active' => MoneyPayment::PAYABLE_CHEQUE,
        ]));

        $moneyPayment = $moneyPayment->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
        $cheque = $moneyPayment->payableCheque;
        $statement = $moneyPayment->getCurrentStatement();

        $this->assertTrue($cheque->isPending());
        $this->assertFalse($cheque->isPaid());
        $this->assertSame($dueDate, Carbon::make($cheque->actual_payment_date)->format('Y-m-d'));
        $this->assertNotNull($statement);
        $this->assertSame($dueDate, Carbon::make($statement->date)->format('Y-m-d'));
        $this->assertEquals($amount, (float) $statement->credit);
        $this->assertEquals(0.0, (float) $statement->debit);
    }

    public function test_cash_expense_unmark_restores_pending_status_and_due_date_statement(): void
    {
        $dueDate = now()->subDays(10)->format('Y-m-d');
        $paymentDate = now()->subDays(5)->format('Y-m-d');
        $amount = 100.0;

        $cashExpense = $this->makePaidCashExpenseCheque($dueDate, $paymentDate, $amount);

        $response = $this->actingAs($this->user)
            ->post(route('cash.expense.payable.cheque.unmark.as.paid', ['company' => $this->company->id]), [
                'cheques' => [$cashExpense->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('view.cash.expense', [
            'company' => $this->company->id,
            'active' => CashExpense::PAYABLE_CHEQUE,
        ]));

        $cashExpense = $cashExpense->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
        $cheque = $cashExpense->payableCheque;
        $statement = $cashExpense->getCurrentStatement();

        $this->assertTrue($cheque->isPending());
        $this->assertFalse($cheque->isPaid());
        $this->assertSame($dueDate, Carbon::make($cheque->actual_payment_date)->format('Y-m-d'));
        $this->assertNotNull($statement);
        $this->assertSame($dueDate, Carbon::make($statement->date)->format('Y-m-d'));
        $this->assertEquals($amount, (float) $statement->credit);
        $this->assertEquals(0.0, (float) $statement->debit);
    }

    public function test_unmark_rejects_a_cheque_that_is_not_paid(): void
    {
        $dueDate = now()->subDays(10)->format('Y-m-d');
        $moneyPayment = $this->makePendingMoneyPaymentCheque($dueDate, 100);

        $this->actingAs($this->user)
            ->from(route('view.money.payment', ['company' => $this->company->id, 'active' => MoneyPayment::PAYABLE_CHEQUE]))
            ->post(route('payable.cheque.unmark.as.paid', ['company' => $this->company->id]), [
                'cheques' => [$moneyPayment->id],
            ])
            ->assertSessionHasErrors('cheques');

        $this->assertTrue($moneyPayment->fresh()->payableCheque->isPending());
    }

    private function makePaidMoneyPaymentCheque(string $dueDate, string $paymentDate, float $amount): MoneyPayment
    {
        $moneyPayment = $this->makePendingMoneyPaymentCheque($dueDate, $amount);
        $this->simulateMarkAsPaid($moneyPayment, $paymentDate);

        return $moneyPayment->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
    }

    private function makePaidCashExpenseCheque(string $dueDate, string $paymentDate, float $amount): CashExpense
    {
        $cashExpense = $this->makePendingCashExpenseCheque($dueDate, $amount);
        $this->simulateMarkAsPaid($cashExpense, $paymentDate);

        return $cashExpense->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
    }

    private function makePendingMoneyPaymentCheque(string $dueDate, float $amount): MoneyPayment
    {
        $supplier = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Unmark Supplier '.uniqid(),
            'is_supplier' => 1,
        ]);

        $moneyPayment = MoneyPayment::create([
            'type' => MoneyPayment::PAYABLE_CHEQUE,
            'money_type' => 'money-payment',
            'partner_id' => $supplier->id,
            'paid_amount' => $amount,
            'amount_in_invoice_currency' => $amount,
            'currency' => $this->currency,
            'payment_currency' => $this->currency,
            'delivery_date' => $dueDate,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'exchange_rate' => 1,
        ]);

        $moneyPayment->payableCheque()->create([
            'status' => PayableCheque::PENDING,
            'cheque_number' => 'UNMARK-'.uniqid(),
            'delivery_bank_id' => $this->bank->id,
            'due_date' => $dueDate,
            'actual_payment_date' => $dueDate,
            'delivery_date' => $dueDate,
            'company_id' => $this->company->id,
            'account_type' => $this->currentAccountTypeId(),
            'account_number' => $this->account->account_number,
        ]);

        $moneyPayment->refresh();
        $this->writeChequeStatement($moneyPayment, $dueDate, $amount);

        return $moneyPayment->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
    }

    private function makePendingCashExpenseCheque(string $dueDate, float $amount): CashExpense
    {
        $cashExpense = CashExpense::create([
            'type' => CashExpense::PAYABLE_CHEQUE,
            'payment_date' => $dueDate,
            'paid_amount' => $amount,
            'amount_in_invoice_currency' => $amount,
            'currency' => $this->currency,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'exchange_rate' => 1,
        ]);

        $cashExpense->payableCheque()->create([
            'status' => PayableCheque::PENDING,
            'cheque_number' => 'UNMARK-CE-'.uniqid(),
            'delivery_bank_id' => $this->bank->id,
            'due_date' => $dueDate,
            'actual_payment_date' => $dueDate,
            'delivery_date' => $dueDate,
            'company_id' => $this->company->id,
            'account_type' => $this->currentAccountTypeId(),
            'account_number' => $this->account->account_number,
        ]);

        $cashExpense->refresh();
        $this->writeChequeStatement($cashExpense, $dueDate, $amount);

        return $cashExpense->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
    }

    /**
     * Same local steps markChequesAsPaid runs (status + statement date),
     * without hitting the HTTP mark-as-paid rules or Odoo.
     *
     * @param  MoneyPayment|CashExpense  $model
     */
    private function simulateMarkAsPaid($model, string $paymentDate): void
    {
        $model->payableCheque->update([
            'status' => PayableCheque::PAID,
            'actual_payment_date' => $paymentDate,
        ]);

        $statement = $model->fresh()->getCurrentStatement();
        $this->assertNotNull($statement, 'Cheque must have a current-account statement before mark-as-paid.');
        $statement->handleFullDateAfterDateEdit($paymentDate, $statement->debit, $statement->credit);

        $model = $model->fresh(['payableCheque', 'currentAccountCreditBankStatement']);
        $this->assertTrue($model->payableCheque->isPaid());
        $this->assertSame($paymentDate, Carbon::make($model->getCurrentStatement()->date)->format('Y-m-d'));
    }

    /**
     * @param  MoneyPayment|CashExpense  $model
     */
    private function writeChequeStatement($model, string $dueDate, float $amount): void
    {
        $accountType = AccountType::find($this->currentAccountTypeId());
        $model->handleCreditStatement(
            $this->company->id,
            $this->bank->id,
            $accountType,
            $this->account->account_number,
            $model instanceof CashExpense ? CashExpense::PAYABLE_CHEQUE : MoneyPayment::PAYABLE_CHEQUE,
            $dueDate,
            $amount,
            null,
            $this->currency
        );
    }

    private function currentAccountTypeId(): int
    {
        $id = AccountType::onlyCurrentAccount()->value('id');
        if (! $id) {
            $this->markTestSkipped('No current-account type in the development database.');
        }

        return (int) $id;
    }
}

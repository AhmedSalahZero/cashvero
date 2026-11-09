<?php

namespace Tests\Feature\Odoo;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;

/**
 * Fake money movements for the down-payment-cheque tests: cheques received into a safe, cheques
 * paid from a current account, and the cash / transfer movements they are compared with.
 *
 * The class using it must also use Tests\Concerns\MakesBanks (for the bank and current account a
 * paid cheque is drawn on). Every Odoo id is made up; the rows live only for the test (TestCase
 * rolls the transaction back).
 *
 *   $cheque  = $this->receivedCheque($company, 'is_customer', ['money_type' => 'down-payment']);
 *   $payment = $this->paidCheque($company, 'is_supplier', ['money_type' => 'down-payment']);
 *   $cash    = $this->receivedMoney($company, MoneyReceived::CASH_IN_SAFE, 'is_customer');
 *   $expense = $this->cashExpenseCheque($company);
 */
trait MakesMoneyCheques
{
    private const PARTNER_FLAGS = [
        'is_customer' => 'customer',
        'is_supplier' => 'supplier',
        'is_employee' => 'employee',
        'is_shareholder' => 'shareholder',
    ];

    private int $fakeOdooId = 700000;

    /** A partner of the given kind (is_customer, is_supplier, is_employee ...), known to Odoo by a fake id. */
    protected function partnerOfType(Company $company, ?string $partnerType): Partner
    {
        $factory = PartnerFactory::new()->forCompany($company);
        $state = self::PARTNER_FLAGS[$partnerType] ?? null;

        if ($state !== null) {
            $factory = $factory->{$state}();
        } elseif ($partnerType !== null) {
            $factory = $factory->state([$partnerType => 1]);
        }

        return $factory->create(['odoo_id' => $this->nextFakeOdooId()]);
    }

    /** A cash safe (branch) wired to a fake Odoo journal and fake payment-method lines. */
    protected function safeBranch(Company $company): Branch
    {
        $id = DB::table('branch')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Test Safe '.$this->nextFakeOdooId(),
            'currency' => 'EGP',
            'odoo_id' => $this->nextFakeOdooId(),
            'odoo_code' => 'SAFE-'.$this->nextFakeOdooId(),
            'journal_id' => $this->nextFakeOdooId(),
            'odoo_inbound_cheque_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_inbound_transfer_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_outbound_cheque_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_outbound_transfer_payment_method_id' => $this->nextFakeOdooId(),
        ]);

        return Branch::findOrFail($id);
    }

    /** A cheque received from a partner of the given kind, sitting in a safe. */
    protected function receivedCheque(Company $company, ?string $partnerType, array $money = [], array $cheque = []): MoneyReceived
    {
        $model = $this->receivedMoney($company, MoneyReceived::CHEQUE, $partnerType, $money);
        $branch = $this->safeBranch($company);

        DB::table('cheques')->insert(array_merge([
            'money_received_id' => $model->id,
            'company_id' => $company->id,
            'status' => 'in-safe',
            'branch_id' => $branch->id,
            'account_type' => (string) $this->accountTypeId('current-account'),
            'cheque_number' => 'CHQ-'.$model->id,
            'due_date' => '2026-03-01',
        ], $cheque));

        return $model->refresh();
    }

    /** Money received of any type (cash in safe, incoming transfer, cheque without its cheque row ...). */
    protected function receivedMoney(Company $company, string $type, ?string $partnerType, array $overrides = []): MoneyReceived
    {
        $partner = $this->partnerOfType($company, $partnerType);

        $id = DB::table('money_received')->insertGetId(array_merge([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'partner_type' => $partnerType ?? 'is_customer',
            'type' => $type,
            'money_type' => 'invoice-settlement',
            'receiving_date' => '2026-02-01',
            'received_amount' => 1000,
            'currency' => 'EGP',
            'receiving_currency' => 'EGP',
            'exchange_rate' => 1,
        ], $overrides));

        return MoneyReceived::findOrFail($id);
    }

    /** A cheque paid to a partner of the given kind, drawn on a current account of the company's bank. */
    protected function paidCheque(Company $company, ?string $partnerType, array $money = [], array $cheque = []): MoneyPayment
    {
        $model = $this->paidMoney($company, MoneyPayment::PAYABLE_CHEQUE, $partnerType, $money);
        [$bank, $account] = $this->chequeAccount($company);

        DB::table('payable_cheques')->insert(array_merge([
            'money_payment_id' => $model->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'delivery_bank_id' => $bank->id,
            'account_type' => (string) $this->accountTypeId('current-account'),
            'account_number' => $account->account_number,
            'cheque_number' => 'PCHQ-'.$model->id,
            'due_date' => '2026-03-01',
            'delivery_date' => '2026-02-01',
        ], $cheque));

        return $model->refresh();
    }

    /** Money paid of any type (cash payment, outgoing transfer, payable cheque without its cheque row ...). */
    protected function paidMoney(Company $company, string $type, ?string $partnerType, array $overrides = []): MoneyPayment
    {
        $partner = $this->partnerOfType($company, $partnerType);

        $id = DB::table('money_payments')->insertGetId(array_merge([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'partner_type' => $partnerType ?? 'is_supplier',
            'type' => $type,
            'money_type' => 'invoice-settlement',
            'delivery_date' => '2026-02-01',
            'paid_amount' => 1000,
            'currency' => 'EGP',
            'payment_currency' => 'EGP',
            'exchange_rate' => 1,
        ], $overrides));

        return MoneyPayment::findOrFail($id);
    }

    /** A cash expense paid by cheque — it has no partner at all. */
    protected function cashExpenseCheque(Company $company): CashExpense
    {
        [$bank, $account] = $this->chequeAccount($company);

        $id = DB::table('cash_expenses')->insertGetId([
            'company_id' => $company->id,
            'type' => CashExpense::PAYABLE_CHEQUE,
            'payment_date' => '2026-02-01',
            'paid_amount' => 500,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);

        DB::table('payable_cheques')->insert([
            'cash_expense_id' => $id,
            'company_id' => $company->id,
            'status' => 'pending',
            'delivery_bank_id' => $bank->id,
            'account_type' => (string) $this->accountTypeId('current-account'),
            'account_number' => $account->account_number,
            'cheque_number' => 'ECHQ-'.$id,
            'due_date' => '2026-03-01',
            'delivery_date' => '2026-02-01',
        ]);

        return CashExpense::findOrFail($id);
    }

    /** @return array{0: \App\Models\FinancialInstitution, 1: \App\Models\FinancialInstitutionAccount} */
    private function chequeAccount(Company $company): array
    {
        $bank = $this->bank($company);
        $account = $this->currentAccount($bank, 'EGP', [
            'odoo_id' => $this->nextFakeOdooId(),
            'journal_id' => $this->nextFakeOdooId(),
            'odoo_inbound_cheque_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_inbound_transfer_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_outbound_cheque_payment_method_id' => $this->nextFakeOdooId(),
            'odoo_outbound_transfer_payment_method_id' => $this->nextFakeOdooId(),
        ]);

        return [$bank, $account];
    }

    private function nextFakeOdooId(): int
    {
        return ++$this->fakeOdooId;
    }
}

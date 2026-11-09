<?php

namespace Tests\Feature\Odoo;

use App\Models\CashExpense;
use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesOdooSettings;
use Tests\TestCase;

/**
 * A down payment received or paid BY CHEQUE has to be recorded in Odoo as a
 * cheque, not as cash.
 *
 * What went wrong: createNonCustomerOrSupplierOdooExpense() routed a cheque
 * to createDownPayment() only when the partner was NOT a customer or
 * supplier. A customer's down-payment cheque therefore fell through to the
 * raw journal entry below it, which debits the BRANCH'S CASH account. The
 * later collection credits Notes Receivable — a different account — so the
 * safe was never relieved:
 *
 *      receive:  Dr 251 Cash On Hand      1,000,000
 *      collect:  Dr Bank                  1,000,000
 *                Cr 406 Notes Receivable  1,000,000
 *
 *      => safe +1,000,000 AND bank +1,000,000 for one 1,000,000 cheque.
 *
 * Verified against the live Odoo 18 Enterprise instance: payment method
 * line 414 ("Cheque Rec" on the Cash On Hand journal) carries outstanding
 * account 406, which is exactly what chequeCollection() credits — so
 * routing the cheque through createDownPayment() makes the two sides net
 * to zero.
 */
class DownPaymentChequeRoutingTest extends TestCase
{
    use MakesBanks;
    use MakesMoneyCheques;
    use MakesOdooSettings;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->odooSetting($this->company);
    }

    private function traitSource(): string
    {
        return file_get_contents(app_path('Traits/Models/HasNonCustomerOrSupplier.php'));
    }

    /** A customer's down payment over a contract, received by cheque — the case the bug was about. */
    private function customerDownPaymentChequeOverAContract(): MoneyReceived
    {
        $contractId = DB::table('contracts')->insertGetId([
            'company_id' => $this->company->id,
            'name' => 'Down Payment Contract',
            'model_type' => 'Customer',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'amount' => 1000000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        return $this->receivedCheque($this->company, 'is_customer', [
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT,
            'contract_id' => $contractId,
            'received_amount' => 1000000,
        ]);
    }

    /* ── the fix itself ───────────────────────────────────────────── */

    public function test_any_cheque_is_routed_to_the_cheque_payment_path(): void
    {
        $src = $this->traitSource();

        $start = strpos($src, 'function createNonCustomerOrSupplierOdooExpense');
        $this->assertNotFalse($start);
        $body = substr($src, $start, 2600);

        // Strip comments: the docblock explains the old predicate by name.
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $body);

        $this->assertMatchesRegularExpression(
            '/if \(\$this->isChequeOrChequePayment\(\)\) \{\s*\$result = \$odooPaymentService->createDownPayment\(\$this\);/',
            $code,
            'Every cheque must take createDownPayment(), which posts to the cheque method line '
            .'and therefore to Notes Receivable — the account the collection clears.'
        );

        $this->assertStringNotContainsString('isChequeAndNotCustomerOrSupplier()', $code,
            'Excluding customers and suppliers is what sent their cheques to the cash account.');
    }

    /**
     * The decisive property: a customer's down-payment cheque must now take
     * the cheque path. Before the fix this predicate was false for exactly
     * this case.
     */
    public function test_a_customer_down_payment_cheque_now_takes_the_cheque_path(): void
    {
        $cheque = $this->customerDownPaymentChequeOverAContract();

        $this->assertSame('is_customer', $cheque->getPartnerType());
        $this->assertNotNull($cheque->contract_id);

        $this->assertTrue($cheque->isChequeOrChequePayment(),
            'This is the predicate the fixed code branches on.');
        $this->assertFalse($cheque->isChequeAndNotCustomerOrSupplier(),
            'And this is the old one — false for a customer, which is why the cheque fell through '
            .'to the cash-account entry.');
    }

    /* ── the blast radius, asserted rather than assumed ───────────── */

    /**
     * Only a cheque whose partner is a customer or supplier AND which
     * reaches this method at all changes behaviour. Everything else is
     * untouched — that is what makes the change safe to ship.
     *
     * A company with one of every kind of movement that can reach (or not
     * reach) the method: only the two customer/supplier down-payment cheques
     * may change path.
     */
    public function test_only_down_payment_cheques_change_behaviour(): void
    {
        $customerDownPaymentCheque = $this->customerDownPaymentChequeOverAContract();
        $supplierDownPaymentCheque = $this->paidCheque($this->company, 'is_supplier', ['money_type' => MoneyPayment::DOWN_PAYMENT]);

        // Cheques that already took the cheque path, or never reach the method.
        $this->receivedCheque($this->company, 'is_customer');                                              // ordinary invoice cheque
        $this->receivedCheque($this->company, 'is_employee', ['money_type' => MoneyReceived::DOWN_PAYMENT, 'transaction_type' => 'pay-loan']);
        $this->receivedCheque($this->company, 'is_shareholder', ['transaction_type' => 'funding-from']);
        $this->paidCheque($this->company, 'is_supplier');                                                   // ordinary bill cheque
        $this->paidCheque($this->company, 'is_employee', ['transaction_type' => 'loan']);

        // Down payments that are not cheques at all.
        $this->receivedMoney($this->company, MoneyReceived::CASH_IN_SAFE, 'is_customer', ['money_type' => MoneyReceived::DOWN_PAYMENT]);
        $this->receivedMoney($this->company, MoneyReceived::INCOMING_TRANSFER, 'is_customer', ['money_type' => MoneyReceived::DOWN_PAYMENT]);
        $this->paidMoney($this->company, MoneyPayment::CASH_PAYMENT, 'is_supplier', ['money_type' => MoneyPayment::DOWN_PAYMENT]);
        $this->paidMoney($this->company, MoneyPayment::OUTGOING_TRANSFER, 'is_supplier', ['money_type' => MoneyPayment::DOWN_PAYMENT]);

        $changed = [];

        foreach ([MoneyReceived::class => 'is_customer', MoneyPayment::class => 'is_supplier'] as $class => $ownType) {
            foreach ($class::where('company_id', $this->company->id)->cursor() as $model) {
                try {
                    $partnerType = $model->getPartnerType();
                    $isDownPayment = $model->isDownPayment();
                } catch (\Throwable $e) {
                    continue;
                }

                // The controller guard: nothing else reaches the method.
                if (! (($partnerType && $partnerType !== $ownType) || $isDownPayment)) {
                    continue;
                }

                $before = $model->isChequeOrChequePayment()
                    && ! in_array($partnerType, ['is_customer', 'is_supplier'], true);
                $after = $model->isChequeOrChequePayment();

                if ($before !== $after) {
                    $changed[] = class_basename($class).'#'.$model->id
                        .' type='.$model->getType().' partner='.($partnerType ?? 'null');
                }
            }
        }

        foreach ($changed as $row) {
            $this->assertStringContainsString('cheque', $row,
                "Only cheques may change path, but this did:\n  {$row}");
        }

        $this->assertLessThanOrEqual(10, count($changed),
            "The change is meant to be surgical. It now affects:\n  ".implode("\n  ", $changed));

        $this->assertSame([
            'MoneyReceived#'.$customerDownPaymentCheque->id.' type=cheque partner=is_customer',
            'MoneyPayment#'.$supplierDownPaymentCheque->id.' type=payable_cheque partner=is_supplier',
        ], $changed, 'Exactly the customer and supplier down-payment cheques change path — nothing else.');
    }

    /** Cash expenses were already on the cheque path and must stay there. */
    public function test_cash_expenses_are_untouched(): void
    {
        $this->cashExpenseCheque($this->company);
        DB::table('cash_expenses')->insert([
            'company_id' => $this->company->id,
            'type' => CashExpense::CASH_PAYMENT,
            'payment_date' => '2026-02-01',
            'paid_amount' => 250,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);

        $differing = [];
        $checked = 0;

        foreach (CashExpense::where('company_id', $this->company->id)->cursor() as $expense) {
            $checked++;
            $before = $expense->isChequeOrChequePayment()
                && ! in_array($expense->getPartnerType(), ['is_customer', 'is_supplier'], true);

            if ($before !== $expense->isChequeOrChequePayment()) {
                $differing[] = $expense->id;
            }
        }

        $this->assertSame(2, $checked);
        $this->assertSame([], $differing,
            'A cash expense has no partner type, so both predicates already agreed — none may change.');
    }

    /**
     * An ordinary cheque that settles an invoice must not reach this method
     * at all: it has its own path (createPayment) and it works today.
     */
    public function test_an_ordinary_invoice_cheque_never_reaches_this_method(): void
    {
        $ordinary = $this->receivedCheque($this->company, 'is_customer', ['money_type' => 'invoice-settlement']);

        $this->assertNull($ordinary->contract_id);
        $this->assertFalse($ordinary->isDownPayment());

        $reaches = ($ordinary->getPartnerType() && $ordinary->getPartnerType() !== 'is_customer')
            || $ordinary->isDownPayment();

        $this->assertFalse($reaches,
            'The controller guard must keep ordinary customer cheques away from this method — '
            .'they are handled by createPayment() and are already correct.');
    }

    /* ── the account the two sides must share ─────────────────────── */

    /**
     * The whole fix rests on one equality: the account the cheque lands in
     * must be the account the collection clears. If someone re-points either
     * side, the duplication comes straight back.
     */
    public function test_the_collection_clears_the_cheques_receivable_account(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/MoneyReceivedController.php'));

        $this->assertStringContainsString('getChequesReceivableId()', $src,
            'The collection must credit the configured cheques-receivable account.');

        $company = Company::whereHas('odooSetting')->findOrFail($this->company->id);

        $this->assertNotNull($company->odooSetting);
        $this->assertNotEmpty($company->odooSetting->getChequesReceivableId(),
            'Without this account configured the collection has nothing to clear against.');
    }
}

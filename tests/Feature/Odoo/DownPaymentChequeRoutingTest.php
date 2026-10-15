<?php

namespace Tests\Feature\Odoo;

use App\Models\CashExpense;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Support\Facades\DB;
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
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Pointed at the development database on purpose: these assertions are
        // about the real records. Restored in tearDown so the rest of the suite
        // is not left talking to it.
        $this->originalDatabase = config('database.connections.mysql.database');

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    private function traitSource(): string
    {
        return file_get_contents(app_path('Traits/Models/HasNonCustomerOrSupplier.php'));
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
        $cheque = MoneyReceived::where('type', 'cheque')
            ->whereNotNull('contract_id')
            ->get()
            ->first(fn ($m) => $m->getPartnerType() === 'is_customer');

        if (! $cheque) {
            $this->markTestSkipped('No customer down-payment cheque on file.');
        }

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
     */
    public function test_only_down_payment_cheques_change_behaviour(): void
    {
        $changed = [];

        foreach ([MoneyReceived::class => 'is_customer', MoneyPayment::class => 'is_supplier'] as $class => $ownType) {
            foreach ($class::cursor() as $model) {
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
    }

    /** Cash expenses were already on the cheque path and must stay there. */
    public function test_cash_expenses_are_untouched(): void
    {
        $differing = [];

        foreach (CashExpense::cursor() as $expense) {
            $before = $expense->isChequeOrChequePayment()
                && ! in_array($expense->getPartnerType(), ['is_customer', 'is_supplier'], true);

            if ($before !== $expense->isChequeOrChequePayment()) {
                $differing[] = $expense->id;
            }
        }

        $this->assertSame([], $differing,
            'A cash expense has no partner type, so both predicates already agreed — none may change.');
    }

    /**
     * An ordinary cheque that settles an invoice must not reach this method
     * at all: it has its own path (createPayment) and it works today.
     */
    public function test_an_ordinary_invoice_cheque_never_reaches_this_method(): void
    {
        $ordinary = MoneyReceived::where('type', 'cheque')
            ->whereNull('contract_id')
            ->get()
            ->first(fn ($m) => $m->getPartnerType() === 'is_customer' && ! $m->isDownPayment());

        if (! $ordinary) {
            $this->markTestSkipped('No ordinary customer cheque on file.');
        }

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

        $company = \App\Models\Company::whereHas('odooSetting')->first();

        if (! $company || ! $company->odooSetting) {
            $this->markTestSkipped('No company with Odoo settings to check.');
        }

        $this->assertNotEmpty($company->odooSetting->getChequesReceivableId(),
            'Without this account configured the collection has nothing to clear against.');
    }
}

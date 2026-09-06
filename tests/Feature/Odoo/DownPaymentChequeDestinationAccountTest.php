<?php

namespace Tests\Feature\Odoo;

use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Services\Api\OdooPayment;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A real customer/supplier's down-payment cheque used to leave
 * destination_account_id empty, so Odoo defaulted to the partner's plain
 * receivable/payable account (e.g. 130470 Accounts Receivable) instead of
 * the configured advance-payment account (Customers Advance Payments /
 * Advanced Deposit - Suppliers) — see OdooPayment::createDownPayment().
 *
 * This is the credit-side twin of DownPaymentChequeRoutingTest, which
 * covers the debit side (cash account vs notes receivable).
 */
class DownPaymentChequeDestinationAccountTest extends TestCase
{
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

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

    /** Builds the payment array createDownPayment() would send to Odoo, without any XML-RPC call. */
    private function buildPaymentData($moneyModel): array
    {
        $company = $moneyModel->company;
        $service = new class($company) extends OdooPayment
        {
            public function __construct(Company $company)
            {
                // Bypasses AuthTrait's constructor (no real Odoo login needed):
                // this test only exercises the pure PHP that shapes $paymentData.
            }
        };

        $ref = new ReflectionMethod(OdooPayment::class, 'buildDownPaymentData');
        $ref->setAccessible(true);

        return $ref->invoke($service, $moneyModel);
    }

    public function test_a_customer_down_payment_cheque_targets_the_advances_from_customers_account(): void
    {
        $cheque = MoneyReceived::where('type', 'cheque')
            ->get()
            ->first(fn ($m) => $m->getPartnerType() === 'is_customer' && $m->isDownPayment());

        if (! $cheque) {
            $this->markTestSkipped('No customer down-payment cheque on file.');
        }

        $setting = $cheque->company->odooSetting;

        if (! $setting || ! $setting->advances_from_customers_id) {
            $this->markTestSkipped('advances_from_customers_id is not configured for this company.');
        }

        $data = $this->buildPaymentData($cheque);

        $this->assertSame(
            (int) $setting->advances_from_customers_id,
            $data['destination_account_id'] ?? null,
            'A customer down-payment cheque must credit the configured Customers Advance Payments account, not the partner default.'
        );

        $this->assertSame(
            $cheque->generateDownPaymentMessage(),
            $data['memo'],
            'The partner-specific memo must survive — only the account changes for a real customer.'
        );
    }

    public function test_a_supplier_down_payment_cheque_targets_the_advances_to_suppliers_account(): void
    {
        $cheque = MoneyPayment::where('type', 'cheque')
            ->get()
            ->first(fn ($m) => $m->getPartnerType() === 'is_supplier' && $m->isDownPayment());

        if (! $cheque) {
            $this->markTestSkipped('No supplier down-payment cheque on file.');
        }

        $setting = $cheque->company->odooSetting;

        if (! $setting || ! $setting->advances_to_suppliers_id) {
            $this->markTestSkipped('advances_to_suppliers_id is not configured for this company.');
        }

        $data = $this->buildPaymentData($cheque);

        $this->assertSame(
            (int) $setting->advances_to_suppliers_id,
            $data['destination_account_id'] ?? null,
            'A supplier down-payment cheque must credit the configured supplier-advance account, not the partner default.'
        );

        $this->assertSame(
            $cheque->generateDownPaymentMessage(),
            $data['memo'],
            'The partner-specific memo must survive — only the account changes for a real supplier.'
        );
    }

    /**
     * The categories the destination_account_id override already served
     * before this change (employee, shareholder, subsidiary, other partner)
     * must keep getting BOTH the account override and the specific ref —
     * this change must be additive, not a replacement.
     *
     * The live data has no down-payment cheque outside customer/supplier to
     * exercise this against, so a real customer cheque is reused with its
     * partner_type overridden in memory (unsaved) — everything else (journal,
     * cheque relation, company config) stays the genuine, working setup.
     */
    public function test_non_customer_or_supplier_categories_keep_their_own_account_and_memo(): void
    {
        $cheque = MoneyReceived::where('type', 'cheque')
            ->get()
            ->first(fn ($m) => $m->getPartnerType() === 'is_customer'
                && $m->isDownPayment()
                && $m->company?->odooSetting);

        if (! $cheque) {
            $this->markTestSkipped('No customer down-payment cheque with Odoo settings configured to base the stand-in on.');
        }

        $stub = $cheque->replicate();
        $stub->id = $cheque->id;
        $stub->partner_type = 'is_employee';

        $odooIdWithRef = $stub->getOdooIdWithRefOfTransaction();

        if (empty($odooIdWithRef['id'])) {
            $this->markTestSkipped('No employee-category account configured for this company.');
        }

        $data = $this->buildPaymentData($stub);

        $this->assertSame((int) $odooIdWithRef['id'], $data['destination_account_id'] ?? null,
            'A non-customer/supplier category must still use its own transaction account.');
        $this->assertSame($odooIdWithRef['ref'], $data['memo'],
            'A non-customer/supplier category must still get the specific ref as memo — unchanged from before this fix.');
    }

    /**
     * A cash expense has no partner type at all, so it was already covered
     * by isChequeAndNotCustomerOrSupplier() before this change and must
     * behave identically — this widening must not touch it.
     */
    public function test_cash_expenses_are_unaffected(): void
    {
        $expense = \App\Models\CashExpense::where('type', 'cheque')
            ->get()
            ->first(fn ($m) => $m->isChequeOrChequePayment());

        if (! $expense) {
            $this->markTestSkipped('No cash expense cheque on file.');
        }

        $this->assertNull($expense->getPartnerType());
        $this->assertTrue($expense->isChequeAndNotCustomerOrSupplier(),
            'A cash expense has no partner type, so it must still take the original branch untouched.');
    }

    /* ── the widening condition itself, independent of live data ─────── */

    public function test_the_widened_condition_covers_exactly_customer_and_supplier_cheques(): void
    {
        $source = file_get_contents(app_path('Services/Api/OdooPayment.php'));
        $start = strpos($source, 'function buildDownPaymentData(');
        $end = strpos($source, 'function createDownPaymentFromSettlement(');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            "in_array(\$moneyModel->getPartnerType(), ['is_customer', 'is_supplier'], true)",
            $body,
            'The new branch must be scoped to exactly is_customer/is_supplier — nothing wider.'
        );

        /**
         * No live down-payment cheque outside customer/supplier exists to
         * exercise this behaviourally (every current caller of
         * createDownPayment() already guarantees isChequeOrChequePayment()),
         * so this is pinned structurally: the new branch must require it
         * explicitly, same as the pre-existing $isChequeAndNotCustomerOrSupplier
         * branch right above it — a customer/supplier down payment settled by
         * cash or bank transfer must NOT get destination_account_id from here.
         */
        $this->assertMatchesRegularExpression(
            '/\$isCustomerOrSupplierDownPaymentCheque = \$moneyModel->isChequeOrChequePayment\(\)\s*'
            .'&& in_array\(\$moneyModel->getPartnerType\(\), \[\'is_customer\', \'is_supplier\'\], true\);/',
            $body,
            'The widened condition must require isChequeOrChequePayment() — a non-cheque customer/supplier '
            .'down payment already gets the right account via the raw journal-entry path and must not be touched here.'
        );

        $this->assertMatchesRegularExpression(
            '/if \(\$isChequeAndNotCustomerOrSupplier\)\s*\{\s*\$paymentData\[\'memo\'\]/',
            $body,
            'The memo override must stay conditional on the OLD predicate only — a real customer/supplier must keep generateDownPaymentMessage().'
        );
    }
}

<?php

namespace Tests\Feature\Balances;

use App\Http\Controllers\BalancesController;
use App\Models\Company;
use App\Models\InternalSettlement;
use Carbon\Carbon;
use Database\Factories\PartnerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Internal settlements, allocated across real invoices.
 *
 * An internal settlement offsets a partner who is both a customer and
 * a supplier against themselves: money comes off their customer
 * invoices and lands on their supplier invoices, same amount, same
 * moment.
 *
 * These tests run against the app's REAL invoice and settlement
 * triggers — the full schema of the test database (cashvero_test),
 * where every table and trigger the migrations build already exists —
 * rather than a stand-in, because the whole design rests on them: the
 * controller never writes a balance, it writes a settlement row and the
 * trigger recomputes net_balance. A test without the triggers would
 * prove nothing about the number that ends up on screen.
 *
 * The company, its partners and invoices are made fresh for each test
 * and rolled back with it (TestCase).
 *
 * @see \App\Models\InternalSettlement
 * @see \App\Http\Controllers\BalancesController::storeInternalSettlement()
 */
class InternalSettlementTest extends TestCase
{
    private Company $company;

    /** A partner who is a customer AND a supplier. */
    private int $dual;

    private int $customerOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany(['main_functional_currency' => 'EGP']);
        $this->dual = PartnerFactory::new()->forCompany($this->company)->customer()->supplier()->create(['name' => 'Ahmed'])->id;
        $this->customerOnly = PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => 'Mona'])->id;

        $this->app->instance('request', Request::create('/en/'.$this->company->id.'/customer-balances/CustomerInvoice', 'GET'));
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function customerInvoice(string $number, float $amount, ?int $partnerId = null): int
    {
        return $this->invoice('customer_invoices', 'customer_id', $number, $amount, $partnerId ?? $this->dual);
    }

    private function supplierInvoice(string $number, float $amount, ?int $partnerId = null): int
    {
        return $this->invoice('supplier_invoices', 'supplier_id', $number, $amount, $partnerId ?? $this->dual);
    }

    private function invoice(string $table, string $partnerColumn, string $number, float $amount, int $partnerId): int
    {
        return (int) DB::table($table)->insertGetId([
            'company_id' => $this->company->id,
            $partnerColumn => $partnerId,
            'invoice_number' => $number,
            'invoice_date' => Carbon::today()->subMonth()->format('Y-m-d'),
            'invoice_due_date' => Carbon::today()->format('Y-m-d'),
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'invoice_amount' => $amount,
        ]);
    }

    private function openBalance(string $table, string $partnerColumn, int $partnerId): float
    {
        return round((float) DB::table($table)->where($partnerColumn, $partnerId)->sum('net_balance'), 2);
    }

    private function customerOpen(?int $partnerId = null): float
    {
        return $this->openBalance('customer_invoices', 'customer_id', $partnerId ?? $this->dual);
    }

    private function supplierOpen(?int $partnerId = null): float
    {
        return $this->openBalance('supplier_invoices', 'supplier_id', $partnerId ?? $this->dual);
    }

    /** This company's internal settlements (the only ones a test can see — see TestCase). */
    private function settlements(): \Illuminate\Database\Eloquent\Builder
    {
        return InternalSettlement::query()->where('company_id', $this->company->id);
    }

    private function controller(): BalancesController
    {
        return app(BalancesController::class);
    }

    private function submit(array $payload, ?InternalSettlement $editing = null)
    {
        $request = Request::create('/en/'.$this->company->id.'/customer-balances/internal-settlement', $editing ? 'PUT' : 'POST', $payload + [
            'partner_id' => $this->dual,
            'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'),
        ]);
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);

        $company = Company::findOrFail($this->company->id);

        return $editing
            ? $this->controller()->updateInternalSettlement($request, $company, $editing)
            : $this->controller()->storeInternalSettlement($request, $company);
    }

    private function failure($response): ?string
    {
        return $response->getSession()->get('fail');
    }

    // ---------------------------------------------------------------
    // the money actually moves
    // ---------------------------------------------------------------

    /** The whole point: one settlement, both balances down by it. */
    public function test_it_takes_the_amount_off_the_customer_and_the_supplier_together(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());

        $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 300],
        ]);

        $this->assertSame(700.0, $this->customerOpen(), '1,000 − 300 settled.');
        $this->assertSame(500.0, $this->supplierOpen(), '800 − 300 settled.');
        $this->assertSame(300.0, $this->settlements()->firstOrFail()->getAmount());
    }

    /** One settlement can be spread over several invoices per side. */
    public function test_one_settlement_can_span_several_invoices_on_each_side(): void
    {
        $c1 = $this->customerInvoice('C-1', 400);
        $c2 = $this->customerInvoice('C-2', 600);
        $s1 = $this->supplierInvoice('S-1', 250);
        $s2 = $this->supplierInvoice('S-2', 900);

        $this->submit([
            'customer_allocations' => [$c1 => 400, $c2 => 100],
            'supplier_allocations' => [$s1 => 250, $s2 => 250],
        ]);

        $this->assertSame(500.0, $this->customerOpen(), '1,000 − 500.');
        $this->assertSame(650.0, $this->supplierOpen(), '1,150 − 500.');
        $this->assertSame(0.0, (float) DB::table('customer_invoices')->where('id', $c1)->value('net_balance'),
            'C-1 was allocated its whole balance, so it is fully settled.');
        $this->assertSame(500.0, (float) DB::table('customer_invoices')->where('id', $c2)->value('net_balance'));
    }

    /**
     * Cents survive the whole round trip.
     *
     * The dialog used to render every figure to whole units, so a
     * settlement of 70,880.01 read as "70,880" on both sides and nobody
     * could see whether the two sides really agreed. The arithmetic was
     * always right - this pins that down, and the presentation tests
     * below pin down what is shown.
     */
    public function test_a_settlement_with_cents_closes_both_invoices_exactly(): void
    {
        $customer = $this->customerInvoice('C-1', 70880.01);
        $supplier = $this->supplierInvoice('S-1', 70880.01);

        $this->submit([
            'customer_allocations' => [$customer => 70880.01],
            'supplier_allocations' => [$supplier => 70880.01],
        ]);

        $this->assertSame(0.0, $this->customerOpen(), 'القروش لازم تتصفّى، مش تسيب باقي');
        $this->assertSame(0.0, $this->supplierOpen());
        $this->assertSame(70880.01, $this->settlements()->firstOrFail()->getAmount());
    }

    /** ... and a cent of mismatch is still refused, not rounded away. */
    public function test_a_single_cent_of_mismatch_is_refused(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $response = $this->submit([
            'customer_allocations' => [$customer => 500.02],
            'supplier_allocations' => [$supplier => 500],
        ]);

        $this->assertNotNull($this->failure($response), 'فرق قرشين مالوش يعدّي');
        $this->assertSame(1000.0, $this->customerOpen(), 'مفيش حاجة اتحركت');
    }

    // ---------------------------------------------------------------
    // what the dialog shows
    // ---------------------------------------------------------------

    /**
     * Settling a balance in full used to strand the settlement that did it.
     *
     * The "Settle" button is gated on can_settle, which is false once the
     * net balance reaches zero - and that button is the only way into the
     * dialog holding the edit and delete actions for settlements already
     * recorded. So the last settlement a partner needed could never be
     * corrected or taken back.
     */
    public function test_a_settlement_that_clears_the_balance_can_still_be_edited_and_deleted(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 1000],
            'supplier_allocations' => [$supplier => 1000],
        ]);

        $settlement = $this->settlements()->firstOrFail();

        // The row now reads zero, so can_settle would be false ...
        $this->assertSame(0.0, $this->customerOpen(), 'المفروض الرصيد اتصفّى بالكامل');

        // ... and yet the settlement must still be reachable and editable.
        $this->submit([
            'customer_allocations' => [$customer => 600],
            'supplier_allocations' => [$supplier => 600],
        ], $settlement->fresh());

        $this->assertSame(400.0, $this->customerOpen(), 'تخفيض التسوية لازم يرجّع الفرق');
        $this->assertSame(600.0, $settlement->fresh()->getAmount());

        $this->controller()->destroyInternalSettlement(
            Company::findOrFail($this->company->id), $settlement->fresh()
        );

        $this->assertSame(1000.0, $this->customerOpen(), 'الحذف لازم يرجّع الرصيد كامل');
        $this->assertSame(0, $this->settlements()->count());
    }

    /** The button that reaches them must not be gated on can_settle alone. */
    public function test_the_settlements_column_stays_reachable_once_nothing_is_left_open(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Balances/Index.vue'));

        preg_match('/v-if="canSettleInternally && ([^"]+)"/', $page, $m);

        $this->assertNotEmpty($m, 'زرار التسوية الداخلية مش موجود بالشكل المتوقع');
        $this->assertStringContainsString('row.settlements', $m[1],
            'الزرار لازم يفضل ظاهر طالما فيه تسويات متسجلة ، حتى لو الرصيد اتصفّى');
        $this->assertNotSame('row.can_settle', trim($m[1]),
            'الاعتماد على can_settle لوحده هو اللي بيخفي الزرار بعد التسوية الكاملة');
    }

    /**
     * Every amount on the balances page goes through one formatter, and
     * it used to drop the decimals - which mattered most in the settle
     * dialog, where the two sides are compared to the cent.
     */
    public function test_amounts_are_rendered_with_two_decimals(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Balances/Index.vue'));

        $this->assertStringContainsString('minimumFractionDigits: 2', $page);
        $this->assertStringContainsString('maximumFractionDigits: 2', $page);
        $this->assertStringNotContainsString('maximumFractionDigits: 0', $page,
            'التقريب لأرقام صحيحة كان بيخفي القروش في مقارنة الجهتين');
    }

    /**
     * The recorded settlements list is how an existing settlement is
     * reached at all. Its edit action was a bare "✎" - a monochrome text
     * glyph, in the muted colour, on a transparent button - and readers
     * did not recognise it as a control.
     */
    public function test_the_recorded_settlements_offer_a_visible_edit_action(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Balances/Index.vue'));

        $this->assertStringContainsString('openSettle(settleTarget, settlement)', $page,
            'لازم يفضل فيه طريقة لفتح تسوية متسجلة للتعديل');
        $this->assertStringNotContainsString('class="cvr-action-btn" :title="$t(\'Edit\')">✎<', $page,
            'الأيقونة العارية دي هي اللي العميل ما شافهاش');
        $this->assertMatchesRegularExpression(
            '/openSettle\(settleTarget, settlement\)"[^>]*>\s*✏️ \{\{ \$t\(.Edit.\) \}\}/s',
            $page,
            'زرار التعديل لازم يبقى مكتوب عليه Edit مش أيقونة لوحدها'
        );
    }

    /** An invoice paid in full by a settlement reads as collected. */
    public function test_an_invoice_settled_in_full_is_marked_collected(): void
    {
        $customer = $this->customerInvoice('C-1', 500);
        $supplier = $this->supplierInvoice('S-1', 500);

        $this->submit([
            'customer_allocations' => [$customer => 500],
            'supplier_allocations' => [$supplier => 500],
        ]);

        $this->assertSame('collected', DB::table('customer_invoices')->where('id', $customer)->value('invoice_status'));
    }

    // ---------------------------------------------------------------
    // what it refuses
    // ---------------------------------------------------------------

    public function test_it_refuses_more_than_an_invoice_has_open(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'customer_allocations' => [$customer => 900],
            'supplier_allocations' => [$supplier => 900],
        ]);

        $this->assertStringContainsString('S-1', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen(), 'Nothing may move when the allocation is refused.');
        $this->assertSame(800.0, $this->supplierOpen());
        $this->assertSame(0, $this->settlements()->count());
    }

    /** Two sides that do not agree are two adjustments, not an offset. */
    public function test_it_refuses_sides_that_do_not_match(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 200],
        ]);

        $this->assertStringContainsString('must match', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());
    }

    public function test_it_refuses_a_partner_who_is_not_also_a_supplier(): void
    {
        $customer = $this->customerInvoice('C-1', 1000, $this->customerOnly);

        $request = Request::create('/x', 'POST', [
            'partner_id' => $this->customerOnly, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'),
            'customer_allocations' => [$customer => 100], 'supplier_allocations' => [],
        ]);
        $request->setLaravelSession(app('session.store'));
        $response = $this->controller()->storeInternalSettlement($request, Company::findOrFail($this->company->id));

        $this->assertStringContainsString('both a customer and a supplier', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen($this->customerOnly));
    }

    public function test_it_refuses_the_main_currency_roll_up(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'currency' => 'main_currency',
            'customer_allocations' => [$customer => 100],
            'supplier_allocations' => [$supplier => 100],
        ]);

        $this->assertNotNull($this->failure($response));
        $this->assertSame(0, $this->settlements()->count());
    }

    /** A settlement with nothing allocated is not a settlement. */
    public function test_it_refuses_an_empty_allocation(): void
    {
        $this->customerInvoice('C-1', 1000);
        $this->supplierInvoice('S-1', 800);

        $response = $this->submit(['customer_allocations' => [], 'supplier_allocations' => []]);

        $this->assertStringContainsString('at least one', (string) $this->failure($response));
    }

    // ---------------------------------------------------------------
    // editing
    // ---------------------------------------------------------------

    /**
     * The edit ceiling must add this settlement's own effect back
     * before measuring. Raising 300 → 900 is legitimate on a 1,000
     * invoice, but the 300 already took the balance to 700 — judged
     * against that, a correct edit would be refused.
     */
    public function test_an_edit_can_raise_its_own_allocation_above_the_current_balance(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 300],
        ]);
        $settlement = $this->settlements()->firstOrFail();
        $this->assertSame(700.0, $this->customerOpen());

        $response = $this->submit([
            'customer_allocations' => [$customer => 900],
            'supplier_allocations' => [$supplier => 900],
        ], $settlement);

        $this->assertNull($this->failure($response), 'Raising the settlement inside the invoice total must be allowed.');
        $this->assertSame(100.0, $this->customerOpen(), '1,000 − 900.');
        $this->assertSame(100.0, $this->supplierOpen());
        $this->assertSame(900.0, $settlement->fresh()->getAmount());
    }

    /** Lowering it gives the difference back to the invoices. */
    public function test_lowering_an_edit_returns_the_difference(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 800],
            'supplier_allocations' => [$supplier => 800],
        ]);
        $settlement = $this->settlements()->firstOrFail();

        $this->submit([
            'customer_allocations' => [$customer => 250],
            'supplier_allocations' => [$supplier => 250],
        ], $settlement);

        $this->assertSame(750.0, $this->customerOpen());
        $this->assertSame(750.0, $this->supplierOpen());
    }

    /** An edit may move the money to entirely different invoices. */
    public function test_an_edit_can_move_the_allocation_to_other_invoices(): void
    {
        $c1 = $this->customerInvoice('C-1', 500);
        $c2 = $this->customerInvoice('C-2', 500);
        $s1 = $this->supplierInvoice('S-1', 500);
        $s2 = $this->supplierInvoice('S-2', 500);

        $this->submit([
            'customer_allocations' => [$c1 => 200],
            'supplier_allocations' => [$s1 => 200],
        ]);
        $settlement = $this->settlements()->firstOrFail();

        $this->submit([
            'customer_allocations' => [$c2 => 200],
            'supplier_allocations' => [$s2 => 200],
        ], $settlement);

        $this->assertSame(500.0, (float) DB::table('customer_invoices')->where('id', $c1)->value('net_balance'),
            'C-1 must be given its money back.');
        $this->assertSame(300.0, (float) DB::table('customer_invoices')->where('id', $c2)->value('net_balance'));
        $this->assertSame(500.0, (float) DB::table('supplier_invoices')->where('id', $s1)->value('net_balance'));
        $this->assertSame(300.0, (float) DB::table('supplier_invoices')->where('id', $s2)->value('net_balance'));
    }

    /** A refused edit must leave the original settlement standing. */
    public function test_a_refused_edit_leaves_the_original_untouched(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);
        $settlement = $this->settlements()->firstOrFail();

        $response = $this->submit([
            'customer_allocations' => [$customer => 500],
            'supplier_allocations' => [$supplier => 300],
        ], $settlement);

        $this->assertStringContainsString('must match', (string) $this->failure($response));
        $this->assertSame(400.0, $settlement->fresh()->getAmount());
        $this->assertSame(600.0, $this->customerOpen(), 'The original 400 is still applied.');
        $this->assertSame(600.0, $this->supplierOpen());
    }

    // ---------------------------------------------------------------
    // deleting
    // ---------------------------------------------------------------

    public function test_deleting_a_settlement_puts_both_sides_back_exactly(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $this->submit([
            'customer_allocations' => [$customer => 275.55],
            'supplier_allocations' => [$supplier => 275.55],
        ]);
        $settlement = $this->settlements()->firstOrFail();

        $this->assertSame(1, DB::table('settlements')->where('company_id', $this->company->id)->count(), 'One allocation row per side before the delete.');
        $this->assertSame(1, DB::table('payment_settlements')->where('company_id', $this->company->id)->count());

        $this->controller()->destroyInternalSettlement(Company::findOrFail($this->company->id), $settlement);

        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());
        $this->assertSame(0, $this->settlements()->count());
        $this->assertSame(0, DB::table('settlements')->where('company_id', $this->company->id)->count(), 'Its allocation rows go with it.');
        $this->assertSame(0, DB::table('payment_settlements')->where('company_id', $this->company->id)->count());
    }

    /** Deleting one settlement must not disturb another. */
    public function test_deleting_one_settlement_leaves_the_others_applied(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit(['customer_allocations' => [$customer => 100], 'supplier_allocations' => [$supplier => 100]]);
        $first = $this->settlements()->orderBy('id')->firstOrFail();
        $this->submit(['customer_allocations' => [$customer => 250], 'supplier_allocations' => [$supplier => 250]]);

        $this->assertSame(650.0, $this->customerOpen(), '1,000 − 100 − 250.');

        $this->controller()->destroyInternalSettlement(Company::findOrFail($this->company->id), $first);

        $this->assertSame(750.0, $this->customerOpen(), 'Only the deleted 100 comes back.');
        $this->assertSame(750.0, $this->supplierOpen());
    }

    // ---------------------------------------------------------------
    // both statements
    // ---------------------------------------------------------------

    /** Credit on the customer's statement, debit on the supplier's. */
    public function test_the_settlement_reads_opposite_ways_on_the_two_statements(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);

        $customerRow = $this->statementRow('CustomerInvoice');
        $this->assertSame(0, $customerRow['debit']);
        $this->assertSame(400.0, $customerRow['credit']);

        $supplierRow = $this->statementRow('SupplierInvoice');
        $this->assertSame(400.0, $supplierRow['debit']);
        $this->assertSame(0, $supplierRow['credit']);
    }

    /** Each statement names the invoices on the OTHER side. */
    public function test_each_statement_names_the_invoices_the_money_reached(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);

        $this->assertStringContainsString('S-1', $this->statementRow('CustomerInvoice')['comment'],
            "The customer statement should say which supplier invoice it paid.");
        $this->assertStringContainsString('C-1', $this->statementRow('SupplierInvoice')['comment'],
            "The supplier statement should say which customer invoice it came from.");
    }

    // ---------------------------------------------------------------
    // routing
    // ---------------------------------------------------------------

    /**
     * The dialog's invoice endpoint must not be swallowed by the
     * single-segment `customer-balances/{modelType}` route above it.
     *
     * It was: registered after the wildcard, the URL matched with
     * modelType = 'internal-settlement-invoices', which
     * BalancesController@index turns into a model class name and dies
     * on — a 500 the dialog could only read as "this partner has no
     * invoices". Route order is invisible at the call site, so it is
     * pinned here.
     */
    public function test_the_invoice_endpoint_is_not_captured_by_the_balances_wildcard(): void
    {
        $matched = app('router')->getRoutes()->match(
            Request::create('/'.$this->company->id.'/customer-balances/internal-settlement-invoices', 'GET')
        );

        $this->assertSame('internal.settlement.invoices', $matched->getName());
    }

    /** modelType becomes a class name, so only the two real ones may reach it. */
    public function test_the_balances_route_only_accepts_a_real_model_type(): void
    {
        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelType) {
            $matched = app('router')->getRoutes()->match(
                Request::create('/'.$this->company->id.'/customer-balances/'.$modelType, 'GET')
            );
            $this->assertSame('view.balances', $matched->getName());
        }

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app('router')->getRoutes()->match(
            Request::create('/'.$this->company->id.'/customer-balances/NotAModel', 'GET')
        );
    }

    /**
     * The dialog reads its endpoint from a prop, and an Inertia prop
     * that is not DECLARED in defineProps is simply absent at runtime —
     * `props.internalSettlementInvoicesUrl` came back undefined, the
     * fetch resolved 'undefined' against the current path, 404'd, and
     * the dialog reported the partner had no invoices.
     *
     * Nothing in a build catches that, so the two halves are pinned
     * together here: what the controller sends and what the component
     * declares.
     */
    public function test_the_page_declares_every_internal_settlement_prop_it_is_sent(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Balances/Index.vue'));
        preg_match('/defineProps\(\s*\{(.*?)\n\}\)/s', $component, $matches);
        $declared = $matches[1] ?? '';

        foreach (['canSettleInternally', 'storeInternalSettlementUrl', 'internalSettlementInvoicesUrl'] as $prop) {
            $this->assertMatchesRegularExpression('/^\s*'.$prop.'\s*:/m', $declared,
                "Balances/Index.vue is sent `{$prop}` but does not declare it, so it is undefined in the component.");
        }
    }

    /** @return array<string, mixed> */
    private function statementRow(string $modelType): array
    {
        $rows = [];
        $index = 0;

        \App\Http\Controllers\CustomerInvoiceDashboardController::appendBalances(
            false, 'EGP', collect(), $index, $rows, $this->dual,
            Carbon::today()->subYear()->format('Y-m-d'), Carbon::today()->format('Y-m-d'),
            [], $modelType, true
        );

        $settlementRows = array_values(array_filter(
            $rows,
            fn ($row) => ($row['document_type'] ?? '') === InternalSettlement::documentType()
        ));

        $this->assertCount(1, $settlementRows, 'Exactly one internal settlement row was expected.');

        return $settlementRows[0];
    }
}

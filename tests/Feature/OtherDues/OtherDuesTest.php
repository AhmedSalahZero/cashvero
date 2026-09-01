<?php

namespace Tests\Feature\OtherDues;

use App\Http\Controllers\OtherDuesController;
use App\Models\Company;
use App\Models\OtherDue;
use App\Models\Partner;
use App\Support\OtherDues\OtherDueStatements;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Other Dues" — non-invoice amounts owed either way with a partner.
 *
 * Three behaviours are specified rather than incidental, and each is
 * pinned here because each would be easy to "tidy away" later:
 *   - two dues from the same partner stay TWO rows and are never summed;
 *   - the movement is dated on the company's opening balance date;
 *   - where it lands depends on the partner type — a real ledger row for
 *     types that have one, and an injected row in the invoice statement
 *     for customers and suppliers, which have none. Never both.
 *
 * Runs inside a rolled-back transaction on the development schema.
 */
class OtherDuesTest extends TestCase
{
    private ?Company $company = null;

    /** Ids at or above this were created by the test and are cleaned up after it. */
    private int $firstNewDueId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::connection('mysql')->table('other_dues')->exists();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development schema not reachable: '.$e->getMessage());
        }

        $this->company = Company::first();

        if (! $this->company) {
            $this->markTestSkipped('No company to exercise.');
        }

        /**
         * The statement models cascade their running balances with raw SQL,
         * which can end an enclosing transaction — so a rollback alone did
         * not isolate these tests and rows leaked into the next one. The
         * high-water mark makes cleanup explicit: anything created during
         * the test is removed by id, and nothing that existed before it is
         * touched.
         */
        $this->firstNewDueId = (int) (OtherDue::max('id') ?? 0) + 1;

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            DB::rollBack();
        } catch (\Throwable $e) {
            // The transaction may already have been ended by the cascade.
        }

        foreach (OtherDue::where('id', '>=', $this->firstNewDueId)->get() as $due) {
            \App\Support\OtherDues\OtherDueStatements::remove($due);
            $due->delete();
        }

        parent::tearDown();
    }

    private function partnerOfType(string $type): Partner
    {
        $partner = Partner::where('company_id', $this->company->id)->where($type, 1)->first();

        if (! $partner) {
            $this->markTestSkipped("No partner with {$type} to exercise.");
        }

        return $partner;
    }

    private function makeDue(Partner $partner, string $type, string $direction, float $amount, string $comment): OtherDue
    {
        $due = OtherDue::create([
            'company_id' => $this->company->id,
            'partner_id' => $partner->id,
            'partner_type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'currency' => 'EGP',
            'comment' => $comment,
        ]);

        OtherDueStatements::sync($due, $this->company);

        return $due;
    }

    /* ── the ledger-backed types ──────────────────────────────────── */

    public function test_a_ledger_partner_gets_a_real_statement_row(): void
    {
        $partner = $this->partnerOfType('is_shareholder');
        $due = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 50000, 'Security deposit');

        $row = \App\Models\ShareholderStatement::where('other_due_id', $due->id)->first();

        $this->assertNotNull($row, 'A shareholder due must write a row on their statement.');
        $this->assertEquals(50000, (float) $row->debit, '"Due from" means they owe us — a debit.');
        $this->assertEquals(0, (float) $row->credit);
        $this->assertSame('Security deposit', $row->comment_en,
            'The comment is the only explanation on the statement; it must survive.');
    }

    public function test_due_to_is_recorded_as_a_credit(): void
    {
        $partner = $this->partnerOfType('is_shareholder');
        $due = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_TO, 12000, 'We owe them');

        $row = \App\Models\ShareholderStatement::where('other_due_id', $due->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals(12000, (float) $row->credit, '"Due to" means we owe them — a credit.');
        $this->assertEquals(0, (float) $row->debit);
    }

    public function test_the_movement_is_dated_on_the_opening_balance_date(): void
    {
        $partner = $this->partnerOfType('is_shareholder');
        $due = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 1000, 'Dated check');

        $row = \App\Models\ShareholderStatement::where('other_due_id', $due->id)->first();

        $this->assertSame(
            OtherDueStatements::dateFor($this->company),
            \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
            'A due describes the opening position, so it carries the opening balance date.'
        );
    }

    /**
     * The behaviour the specification is most explicit about.
     */
    public function test_two_dues_for_the_same_partner_are_never_merged(): void
    {
        $partner = $this->partnerOfType('is_shareholder');
        $first = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 50000, 'Security deposit');
        $second = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 12000, 'Retention');

        $rows = \App\Models\ShareholderStatement::whereIn('other_due_id', [$first->id, $second->id])->get();

        $this->assertCount(2, $rows, 'Each due is its own row — they must not be summed into one.');
        $this->assertEqualsCanonicalizing(
            ['Security deposit', 'Retention'],
            $rows->pluck('comment_en')->all(),
            'Merging would lose the per-due comments, which is the reason for separate rows.'
        );
    }

    public function test_removing_one_due_leaves_the_other_alone(): void
    {
        $partner = $this->partnerOfType('is_shareholder');
        $first = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 50000, 'First');
        $second = $this->makeDue($partner, 'is_shareholder', OtherDue::DUE_FROM, 12000, 'Second');

        OtherDueStatements::remove($first);

        $this->assertSame(0, \App\Models\ShareholderStatement::where('other_due_id', $first->id)->count());
        $this->assertSame(1, \App\Models\ShareholderStatement::where('other_due_id', $second->id)->count(),
            'Removing one due must not touch another partner row.');
    }



    /* ── wiring ───────────────────────────────────────────────────── */

    public function test_the_permission_is_grantable(): void
    {
        $this->assertTrue(PermissionRegistry::has('other_due.view'),
            'Without a registry entry no role could ever be given this screen.');
        $this->assertTrue(PermissionRegistry::has('other_due.update'));
    }

    public function test_the_ledger_backed_partner_types_are_offered(): void
    {
        $offered = array_keys(OtherDuesController::PARTNER_TYPES);

        foreach (['is_subsidiary_company', 'is_shareholder', 'is_employee', 'is_other_partner'] as $type) {
            $this->assertContains($type, $offered,
                "{$type} keeps a statement of its own, so a due against it can be read back.");
        }
    }

    /**
     * Each offered type must either have a ledger, or be one the invoice
     * statement covers. A type in neither would accept a due that then
     * appears nowhere at all.
     */
    public function test_every_offered_type_has_somewhere_to_show(): void
    {
        $invoiceBacked = ['is_customer', 'is_supplier'];
        $orphans = [];

        foreach (array_keys(OtherDuesController::PARTNER_TYPES) as $type) {
            if (! array_key_exists($type, OtherDue::LEDGER_STATEMENTS) && ! in_array($type, $invoiceBacked, true)) {
                $orphans[] = $type;
            }
        }

        $this->assertSame([], $orphans,
            "These partner types have no statement to appear in:\n  ".implode("\n  ", $orphans));
    }

    /* ── store(): the repeater save path ──────────────────────────── */

    private function store(array $rows)
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', ['rows' => $rows]);
        $request->setLaravelSession(app('session.store'));

        return (new OtherDuesController)->store($this->company, $request);
    }

    public function test_saving_the_repeater_writes_every_row(): void
    {
        $shareholder = $this->partnerOfType('is_shareholder');
        $other = $this->partnerOfType('is_other_partner');

        $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 50000, 'currency' => 'EGP', 'comment' => 'Deposit'],
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 12000, 'currency' => 'EGP', 'comment' => 'Retention'],
            ['direction' => OtherDue::DUE_TO, 'partner_type' => 'is_other_partner', 'partner_id' => $other->id,
             'amount' => 8000, 'currency' => 'EGP', 'comment' => 'Owed to other partner'],
        ]);

        $this->assertSame(3, $this->duesCreatedHere(),
            'Three rows in, three dues saved — the two shareholder rows must not collapse into one.');

        $this->assertSame(2, \App\Models\ShareholderStatement::whereNotNull('other_due_id')->count(),
            'Both shareholder dues get their own ledger row.');
    }

    /**
     * The repeater is saved whole, so a row dropped from the payload must
     * take its statement movement with it.
     */
    public function test_a_removed_row_takes_its_statement_movement_with_it(): void
    {
        $shareholder = $this->partnerOfType('is_shareholder');

        $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 50000, 'currency' => 'EGP', 'comment' => 'Keep'],
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 12000, 'currency' => 'EGP', 'comment' => 'Drop'],
        ]);
        $this->assertSame(2, \App\Models\ShareholderStatement::whereNotNull('other_due_id')->count());

        $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 50000, 'currency' => 'EGP', 'comment' => 'Keep'],
        ]);

        $rows = \App\Models\ShareholderStatement::whereNotNull('other_due_id')->get();
        $this->assertCount(1, $rows, 'The dropped row must leave no movement behind.');
        $this->assertSame('Keep', $rows->first()->comment_en);
    }

    /**
     * A partner must genuinely carry the flag the row claims, or the due
     * would land in a statement that partner does not belong to.
     */
    public function test_a_partner_of_the_wrong_type_is_refused(): void
    {
        $customer = Partner::where('company_id', $this->company->id)
            ->where('is_customer', 1)->where('is_shareholder', 0)->first();

        if (! $customer) {
            $this->markTestSkipped('No customer that is not also a shareholder.');
        }

        $response = $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $customer->id,
             'amount' => 1000, 'currency' => 'EGP', 'comment' => 'Wrong type'],
        ]);

        $this->assertSame(0, $this->duesCreatedHere(),
            'Nothing may be saved when the partner is not of the claimed type.');
    }

    public function test_a_foreign_currency_row_requires_a_rate(): void
    {
        $shareholder = $this->partnerOfType('is_shareholder');
        $foreign = $this->company->getMainFunctionalCurrency() === 'USD' ? 'EGP' : 'USD';

        $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 1000, 'currency' => $foreign, 'comment' => 'No rate'],
        ]);

        $this->assertSame(0, $this->duesCreatedHere(),
            'Without a rate the due cannot be valued against anything else, so it must be refused.');
    }

    public function test_a_foreign_currency_row_is_valued_at_its_rate(): void
    {
        $shareholder = $this->partnerOfType('is_shareholder');
        $foreign = $this->company->getMainFunctionalCurrency() === 'USD' ? 'EGP' : 'USD';

        $this->store([
            ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_shareholder', 'partner_id' => $shareholder->id,
             'amount' => 1000, 'currency' => $foreign, 'exchange_rate' => 50, 'comment' => 'With rate'],
        ]);

        $due = OtherDue::where('company_id', $this->company->id)->first();

        $this->assertNotNull($due);
        $this->assertEquals(50000, $due->getAmountInMainCurrency(),
            '1,000 at a rate of 50 is 50,000 in the main currency.');
    }


    /** The partner picker has to be searchable — the lists run to hundreds. */
    public function test_the_partner_picker_is_searchable(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/OtherDues/Form.vue'));

        $this->assertStringContainsString("import SearchableSelect from '@/Components/SearchableSelect.vue'", $vue,
            'Use the searchable select the statements screens already use, not a plain dropdown.');
        $this->assertMatchesRegularExpression(
            '/<SearchableSelect\s+v-model="row\.partner_id"/',
            $vue,
            'The partner field is the one that needs searching.'
        );
    }

    /* ── customers and suppliers are deliberately not offered ─────── */

    /**
     * The decision this pins: a due may only be recorded against a partner
     * type that keeps a statement. Customers and suppliers keep none —
     * theirs is derived from invoices — so offering them would accept a
     * movement that then appears nowhere.
     */
    public function test_customers_and_suppliers_are_not_offered(): void
    {
        $offered = array_keys(OtherDuesController::PARTNER_TYPES);

        $this->assertNotContains('is_customer', $offered,
            'A customer keeps no partner statement, so a due against one could not be read back.');
        $this->assertNotContains('is_supplier', $offered,
            'A supplier keeps no partner statement either.');
    }

    public function test_a_customer_is_refused_even_if_sent_directly(): void
    {
        $customer = $this->partnerOfType('is_customer');

        // The validation rule rejects the type outright, so this throws
        // rather than returning — which is the strongest possible refusal.
        try {
            $this->store([
                ['direction' => OtherDue::DUE_FROM, 'partner_type' => 'is_customer', 'partner_id' => $customer->id,
                 'amount' => 5000, 'currency' => 'EGP', 'comment' => 'Should not save'],
            ]);
            $this->fail('A customer must be refused, not saved.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('rows.0.partner_type', $e->errors());
        }

        $this->assertSame(0, $this->duesCreatedHere(),
            'Nothing may be written for a partner type that keeps no statement.');
    }

    /**
     * The invoice statement must be left exactly as it was — the earlier
     * version injected dues into it, and that has been withdrawn.
     */
    public function test_the_invoice_statement_carries_no_other_dues(): void
    {
        $source = file_get_contents(app_path('Models/Traits/Controllers/HasBalances.php'));

        $this->assertStringNotContainsString('OtherDue', $source,
            'The balances trait must not read Other Dues: customer and supplier statements are '
            .'built from invoices alone.');
        $this->assertStringNotContainsString('appendOtherDues', $source);
    }

    /** Only the dues this test created — never anything already on file. */
    private function duesCreatedHere(): int
    {
        return OtherDue::where('company_id', $this->company->id)
            ->where('id', '>=', $this->firstNewDueId)
            ->count();
    }
}

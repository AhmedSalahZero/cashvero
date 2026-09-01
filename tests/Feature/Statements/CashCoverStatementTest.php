<?php

namespace Tests\Feature\Statements;

use App\Http\Controllers\CashCoverStatementController;
use App\Models\Company;
use App\Support\Permissions\PermissionRegistry;
use App\Support\SidebarMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cash Cover Statement — the money the bank FREEZES behind a letter of
 * guarantee or credit, as opposed to the instruments themselves.
 *
 * The behaviours pinned here are the ones the report would be wrong
 * without: an empty bank must mean EVERY bank rather than a NULL filter,
 * the KPIs must describe the whole filtered range rather than the page on
 * screen, and an incomplete filter must refuse rather than quietly report
 * on everything.
 */
class CashCoverStatementTest extends TestCase
{
    private ?Company $company = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::connection('mysql')->table('letter_of_guarantee_cash_cover_statements')->exists();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development schema not reachable: '.$e->getMessage());
        }

        $this->company = Company::whereHas('letterOfGuaranteeIssuances')->first() ?: Company::first();

        if (! $this->company) {
            $this->markTestSkipped('No company to exercise.');
        }
    }

    /** @return object{currency:string,min:string,max:string,bank:int}|null */
    private function coverFacts(): ?object
    {
        $row = DB::connection('mysql')->table('letter_of_guarantee_cash_cover_statements')
            ->where('company_id', $this->company->id)->first();

        if (! $row) {
            return null;
        }

        return (object) [
            'currency' => $row->currency,
            'bank' => (int) $row->financial_institution_id,
            'min' => DB::connection('mysql')->table('letter_of_guarantee_cash_cover_statements')
                ->where('company_id', $this->company->id)->min('date'),
            'max' => DB::connection('mysql')->table('letter_of_guarantee_cash_cover_statements')
                ->where('company_id', $this->company->id)->max('date'),
        ];
    }

    private function runReport(array $query)
    {
        $request = Request::create('/', 'GET', $query);

        return (new CashCoverStatementController)->result($this->company, $request);
    }

    private function propsOf($response): array
    {
        $this->assertInstanceOf(\Inertia\Response::class, $response,
            'Expected a rendered report; a redirect means the filters were refused.');

        return $response->toResponse(Request::create('/'))->getOriginalContent()->getData()['page']['props'];
    }

    public function test_the_filter_screen_renders(): void
    {
        $response = (new CashCoverStatementController)->index($this->company);
        $props = $this->propsOf($response);

        $this->assertNotEmpty($props['instrumentTypes'], 'LG and LC must both be offerable.');
        $this->assertNotEmpty($props['currencies']);
        $this->assertArrayHasKey('banks', $props);
    }

    public function test_both_instruments_are_offered(): void
    {
        $props = $this->propsOf((new CashCoverStatementController)->index($this->company));
        $values = array_column($props['instrumentTypes'], 'value');

        $this->assertContains('LetterOfGuarantee', $values);
        $this->assertContains('LetterOfCredit', $values);
    }

    /**
     * The distinguishing option: leaving the bank empty must widen the
     * report to every bank, not filter on NULL and return nothing.
     */
    public function test_an_empty_bank_means_every_bank(): void
    {
        $facts = $this->coverFacts();

        if (! $facts) {
            $this->markTestSkipped('No cash cover rows to exercise.');
        }

        $allBanks = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => $facts->currency,
            'start_date' => $facts->min,
            'end_date' => $facts->max,
        ]));

        $oneBank = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => $facts->currency,
            'start_date' => $facts->min,
            'end_date' => $facts->max,
            'financial_institution_id' => $facts->bank,
        ]));

        $this->assertSame(__('All Banks'), $allBanks['bankName']);
        $this->assertNotSame(__('All Banks'), $oneBank['bankName'],
            'Choosing a bank must name that bank.');

        $this->assertGreaterThanOrEqual(
            $oneBank['kpis']['transactionCount'],
            $allBanks['kpis']['transactionCount'],
            'Every bank must cover at least as many movements as one of them.'
        );
    }

    /**
     * KPIs describe the whole filtered range. If they were computed from
     * the visible page, turning the page would change them.
     */
    public function test_the_totals_describe_the_whole_range_not_the_page(): void
    {
        $facts = $this->coverFacts();

        if (! $facts) {
            $this->markTestSkipped('No cash cover rows to exercise.');
        }

        $props = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => $facts->currency,
            'start_date' => $facts->min,
            'end_date' => $facts->max,
        ]));

        $expected = DB::connection('mysql')->table('letter_of_guarantee_cash_cover_statements')
            ->where('company_id', $this->company->id)
            ->where('currency', $facts->currency)
            ->whereBetween('date', [$facts->min, $facts->max])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) cr')
            ->first();

        $this->assertEquals($expected->c, $props['kpis']['transactionCount']);
        $this->assertEqualsWithDelta((float) $expected->d, $props['kpis']['totalDebit'], 0.01);
        $this->assertEqualsWithDelta((float) $expected->cr, $props['kpis']['totalCredit'], 0.01);
    }

    /**
     * A narrowed range must actually exclude rows.
     *
     * Written this way after a weaker version passed while the date
     * filter was removed entirely: it compared the full range against
     * itself, so dropping the filter changed nothing. The range used here
     * is chosen from the data so that rows are provably left out.
     */
    public function test_a_narrowed_range_excludes_rows_outside_it(): void
    {
        $facts = $this->coverFacts();

        if (! $facts) {
            $this->markTestSkipped('No cash cover rows to exercise.');
        }

        $table = 'letter_of_guarantee_cash_cover_statements';

        $total = DB::connection('mysql')->table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', $facts->currency)
            ->count();

        // The earliest date that still leaves rows behind it.
        $cutoff = DB::connection('mysql')->table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', $facts->currency)
            ->where('date', '>', $facts->min)
            ->min('date');

        if (! $cutoff || $total < 2) {
            $this->markTestSkipped('Not enough spread in the data to narrow a range.');
        }

        $excluded = DB::connection('mysql')->table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', $facts->currency)
            ->where('date', '<', $cutoff)
            ->count();

        $this->assertGreaterThan(0, $excluded, 'The chosen cutoff must leave something out.');

        $props = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => $facts->currency,
            'start_date' => $cutoff,
            'end_date' => $facts->max,
        ]));

        $this->assertSame(
            $total - $excluded,
            $props['kpis']['transactionCount'],
            'Rows dated before the start of the range must not be counted.'
        );
        $this->assertLessThan($total, $props['kpis']['transactionCount']);
    }

    /**
     * @dataProvider incompleteFilterProvider
     */
    public function test_an_incomplete_filter_is_refused(array $query): void
    {
        $response = $this->runReport($query);

        $this->assertNotInstanceOf(\Inertia\Response::class, $response,
            'A missing filter must refuse, not silently report on everything.');
    }

    public static function incompleteFilterProvider(): array
    {
        return [
            'no instrument' => [['currency' => 'EGP', 'start_date' => '2020-01-01', 'end_date' => '2030-01-01']],
            'unknown instrument' => [['instrument_type' => 'Nonsense', 'currency' => 'EGP', 'start_date' => '2020-01-01', 'end_date' => '2030-01-01']],
            'no currency' => [['instrument_type' => 'LetterOfGuarantee', 'start_date' => '2020-01-01', 'end_date' => '2030-01-01']],
            'no start date' => [['instrument_type' => 'LetterOfGuarantee', 'currency' => 'EGP', 'end_date' => '2030-01-01']],
            'no end date' => [['instrument_type' => 'LetterOfGuarantee', 'currency' => 'EGP', 'start_date' => '2020-01-01']],
        ];
    }

    /* ── wiring ───────────────────────────────────────────────────── */

    public function test_the_permission_is_grantable(): void
    {
        $this->assertTrue(PermissionRegistry::has('cash_cover_statement.view'),
            'Without a registry entry, no role could ever be given this report.');
    }

    public function test_it_appears_in_the_sidebar_beside_the_lg_lc_statement(): void
    {
        $source = file_get_contents(app_path('Support/SidebarMenu.php'));

        $this->assertMatchesRegularExpression(
            "/LG & LC Statement.*\n.*Cash Cover Statement/",
            $source,
            'The report has to be reachable, and it belongs directly under the LG & LC Statement.'
        );
    }

    public function test_both_cash_cover_tables_are_readable(): void
    {
        foreach ([
            'letter_of_guarantee_cash_cover_statements',
            'letter_of_credit_cash_cover_statements',
        ] as $table) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable($table),
                "{$table} is the report's data source."
            );

            foreach (['date', 'currency', 'financial_institution_id', 'debit', 'credit', 'beginning_balance', 'end_balance'] as $column) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Schema::connection('mysql')->hasColumn($table, $column),
                    "{$table}.{$column} is read by the report."
                );
            }
        }
    }
}

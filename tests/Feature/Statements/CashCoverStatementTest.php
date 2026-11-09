<?php

namespace Tests\Feature\Statements;

use App\Http\Controllers\CashCoverStatementController;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LetterOfGuaranteeIssuance;
use App\Support\Permissions\PermissionRegistry;
use App\Support\SidebarMenu;
use Database\Factories\LetterOfCreditIssuanceFactory;
use Database\Factories\LetterOfGuaranteeIssuanceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesBanks;
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
 *
 * The company, its two banks, its LGs and every cash cover movement are made
 * by the test (rolled back afterwards). The statement trigger works out each
 * row's beginning/end balance on insert, so rows go in in date order.
 *
 *   EGP LG cover (the fixture every test reads)
 *     bank A  2025-01-10  debit 10,000   issuance
 *     bank B  2025-02-01  debit 20,000   issuance
 *     bank A  2025-03-15  debit  5,000   increase
 *     bank B  2025-05-20  credit 20,000  cancellation
 *     bank A  2025-06-30  credit  4,000  decrease
 *   noise that must never be counted: a USD LG row at bank A, an EGP LC
 *   cover row at bank A, and an EGP LG row of another company.
 */
class CashCoverStatementTest extends TestCase
{
    use MakesBanks;

    private const CURRENCY = 'EGP';

    private const FIRST_DATE = '2025-01-10';

    private const LAST_DATE = '2025-06-30';

    private Company $company;

    private FinancialInstitution $bankA;

    private FinancialInstitution $bankB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->bankA = $this->bank($this->company);
        $this->bankB = $this->bank($this->company);

        $lgA = LetterOfGuaranteeIssuanceFactory::new()->at($this->bankA)->create(['source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]);
        $lgB = LetterOfGuaranteeIssuanceFactory::new()->at($this->bankB)->create(['source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]);
        $lgUsd = LetterOfGuaranteeIssuanceFactory::new()->at($this->bankA)->create(['source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER, 'lg_currency' => 'USD']);

        $this->lgCover($lgA, '2025-01-10', debit: 10000, type: 'debit-lg-amount');
        $this->lgCover($lgB, '2025-02-01', debit: 20000, type: 'debit-lg-amount');
        $this->lgCover($lgUsd, '2025-02-15', debit: 300, type: 'debit-lg-amount', currency: 'USD');
        $this->lgCover($lgA, '2025-03-15', debit: 5000, type: 'debit-lg-amount');
        $this->lgCover($lgB, '2025-05-20', credit: 20000, type: LetterOfGuaranteeIssuance::FOR_CANCELLATION);
        $this->lgCover($lgA, '2025-06-30', credit: 4000, type: LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED);

        // An LC's cover lives in its own table and must never reach the LG statement.
        $lc = LetterOfCreditIssuanceFactory::new()->at($this->bankA)->create(['source' => 'hundred-percentage-cash-cover']);
        DB::table('letter_of_credit_cash_cover_statements')->insert([
            'company_id' => $this->company->id,
            'letter_of_credit_issuance_id' => $lc->id,
            'financial_institution_id' => $this->bankA->id,
            'source' => 'hundred-percentage-cash-cover',
            'lc_type' => $lc->lc_type,
            'type' => 'debit-lc-amount',
            'currency' => self::CURRENCY,
            'date' => '2025-04-01',
            'full_date' => '2025-04-01 10:00:00',
            'debit' => 777,
            'credit' => 0,
        ]);

        // Another company's cover on the same dates, in the same currency.
        $otherBank = $this->bank($this->makeCompany());
        $this->lgCover(LetterOfGuaranteeIssuanceFactory::new()->at($otherBank)->create(['source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]), '2025-03-01', debit: 99999, type: 'debit-lg-amount');
    }

    /* ── fixtures ─────────────────────────────────────────────────── */

    private function lgCover(LetterOfGuaranteeIssuance $lg, string $date, float $debit = 0, float $credit = 0, string $type = 'debit-lg-amount', ?string $currency = null): void
    {
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => $lg->company_id,
            'letter_of_guarantee_issuance_id' => $lg->id,
            'financial_institution_id' => $lg->financial_institution_id,
            'source' => $lg->source,
            'lg_type' => $lg->lg_type,
            'type' => $type,
            'currency' => $currency ?? self::CURRENCY,
            'date' => $date,
            'full_date' => $date.' 10:00:00',
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    /** One EGP cover movement a day at bank A through 2024 — more rows than one page holds. */
    private function manySmallCoverMovements(int $count, float $amount): void
    {
        $lg = LetterOfGuaranteeIssuanceFactory::new()->at($this->bankA)->create(['source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER, 'lg_type' => 'final-lgs']);

        for ($day = 0; $day < $count; $day++) {
            $this->lgCover($lg, \Carbon\Carbon::parse('2024-01-01')->addDays($day)->toDateString(), debit: $amount);
        }
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
        $this->assertEqualsCanonicalizing([$this->bankA->id, $this->bankB->id], array_column($props['banks'], 'value'),
            'Exactly the banks of this company are offered.');
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
        $allBanks = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => self::CURRENCY,
            'start_date' => self::FIRST_DATE,
            'end_date' => self::LAST_DATE,
        ]));

        $oneBank = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => self::CURRENCY,
            'start_date' => self::FIRST_DATE,
            'end_date' => self::LAST_DATE,
            'financial_institution_id' => $this->bankA->id,
        ]));

        $this->assertSame(__('All Banks'), $allBanks['bankName']);
        $this->assertNotSame(__('All Banks'), $oneBank['bankName'],
            'Choosing a bank must name that bank.');
        $this->assertSame($this->bankA->getName(), $oneBank['bankName']);

        $this->assertGreaterThanOrEqual(
            $oneBank['kpis']['transactionCount'],
            $allBanks['kpis']['transactionCount'],
            'Every bank must cover at least as many movements as one of them.'
        );
        $this->assertSame(5, $allBanks['kpis']['transactionCount'], 'Both banks: 3 movements at A + 2 at B.');
        $this->assertSame(3, $oneBank['kpis']['transactionCount'], 'Bank A alone.');
    }

    /**
     * KPIs describe the whole filtered range. If they were computed from
     * the visible page, turning the page would change them — so the range
     * here holds more movements than one page (50) shows.
     */
    public function test_the_totals_describe_the_whole_range_not_the_page(): void
    {
        $this->manySmallCoverMovements(60, 100);

        $props = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => self::CURRENCY,
            'start_date' => '2024-01-01',
            'end_date' => self::LAST_DATE,
        ]));

        $expected = DB::table('letter_of_guarantee_cash_cover_statements')
            ->where('company_id', $this->company->id)
            ->where('currency', self::CURRENCY)
            ->whereBetween('date', ['2024-01-01', self::LAST_DATE])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) cr')
            ->first();

        $this->assertLessThan($expected->c, count($props['paginator']['data']), 'The range must be longer than the page on screen.');

        $this->assertEquals($expected->c, $props['kpis']['transactionCount']);
        $this->assertEqualsWithDelta((float) $expected->d, $props['kpis']['totalDebit'], 0.01);
        $this->assertEqualsWithDelta((float) $expected->cr, $props['kpis']['totalCredit'], 0.01);

        // The same figures, worked out by hand: 60 × 100 + the five fixture movements.
        $this->assertSame(65, $props['kpis']['transactionCount']);
        $this->assertEqualsWithDelta(6000 + 35000, $props['kpis']['totalDebit'], 0.01);
        $this->assertEqualsWithDelta(24000, $props['kpis']['totalCredit'], 0.01);
    }

    /**
     * A narrowed range must actually exclude rows.
     *
     * Written this way after a weaker version passed while the date
     * filter was removed entirely: it compared the full range against
     * itself, so dropping the filter changed nothing. The range used here
     * starts after the first movement, so rows are provably left out.
     */
    public function test_a_narrowed_range_excludes_rows_outside_it(): void
    {
        $table = 'letter_of_guarantee_cash_cover_statements';

        $total = DB::table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', self::CURRENCY)
            ->count();

        // The earliest date that still leaves rows behind it.
        $cutoff = DB::table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', self::CURRENCY)
            ->where('date', '>', self::FIRST_DATE)
            ->min('date');

        $this->assertSame('2025-02-01', $cutoff);
        $this->assertSame(5, $total);

        $excluded = DB::table($table)
            ->where('company_id', $this->company->id)
            ->where('currency', self::CURRENCY)
            ->where('date', '<', $cutoff)
            ->count();

        $this->assertGreaterThan(0, $excluded, 'The chosen cutoff must leave something out.');

        $props = $this->propsOf($this->runReport([
            'instrument_type' => 'LetterOfGuarantee',
            'currency' => self::CURRENCY,
            'start_date' => $cutoff,
            'end_date' => self::LAST_DATE,
        ]));

        $this->assertSame(
            $total - $excluded,
            $props['kpis']['transactionCount'],
            'Rows dated before the start of the range must not be counted.'
        );
        $this->assertLessThan($total, $props['kpis']['transactionCount']);
        $this->assertEqualsWithDelta(25000, $props['kpis']['totalDebit'], 0.01, 'The 10,000 of 10 January is outside the range.');
    }

    #[DataProvider('incompleteFilterProvider')]
    public function test_an_incomplete_filter_is_refused(array $query): void
    {
        // The complete filter over the same range does find the movements, so a refusal below is
        // the missing filter's doing, not an empty range.
        $this->assertInstanceOf(\Inertia\Response::class, $this->runReport(
            ['instrument_type' => 'LetterOfGuarantee', 'currency' => 'EGP', 'start_date' => '2020-01-01', 'end_date' => '2030-01-01']
        ));

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

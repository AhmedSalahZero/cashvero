<?php

namespace Tests\Feature\ContractDashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Services\ContractDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Contract Dashboard's arithmetic, on numbers small enough to check
 * by hand.
 *
 * Every expected figure below is written out as the sum it stands for,
 * so a wrong total shows which contract or invoice caused it.
 *
 * @see \App\Services\ContractDashboardService
 */
class ContractDashboardServiceTest extends TestCase
{
    private const COMPANY = 500;

    private const CUSTOMER_A = 10;   // two EGP contracts

    private const CUSTOMER_B = 11;   // one EGP contract, one USD

    /** @var list<string> */
    private array $tables = ['customer_invoices', 'contracts', 'partners', 'companies'];

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->today = Carbon::today()->format('Y-m-d');

        $this->dropTables();

        Schema::create('companies', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('main_functional_currency')->nullable();
        });
        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
        });
        Schema::create('contracts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('model_type')->nullable();
            $table->string('status')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency')->nullable();
            $table->decimal('exchange_rate', 12, 3)->default(1);
        });
        Schema::create('customer_invoices', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('contract_code')->nullable();
            $table->date('invoice_due_date')->nullable();
            $table->string('currency')->nullable();
            $table->boolean('is_canceled')->default(0);
            $table->string('invoice_amount')->nullable();
            $table->string('net_invoice_amount')->nullable();
            $table->decimal('total_collected_amount', 18, 5)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_withhold_amount', 18, 5)->default(0);
            $table->string('net_balance')->nullable();
            $table->string('invoice_amount_in_main_currency')->nullable();
            $table->decimal('total_collected_amount_in_main_currency', 18, 5)->default(0);
            $table->string('net_balance_in_main_currency')->nullable();
        });

        DB::table('companies')->insert(['id' => self::COMPANY, 'main_functional_currency' => 'EGP']);
        DB::table('partners')->insert([
            ['id' => self::CUSTOMER_A, 'company_id' => self::COMPANY, 'name' => 'Customer A'],
            ['id' => self::CUSTOMER_B, 'company_id' => self::COMPANY, 'name' => 'Customer B'],
        ]);
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

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function contract(array $attributes): int
    {
        DB::table('contracts')->insert($attributes + [
            'company_id' => self::COMPANY,
            'model_type' => 'Customer',
            'status' => Contract::RUNNING,
            'partner_id' => self::CUSTOMER_A,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'amount' => 0,
            'end_date' => Carbon::today()->addYear()->format('Y-m-d'),
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * VAT is 10% here so every figure below stays easy to check:
     * net_invoice_amount = invoice_amount × 1.1.
     */
    private function invoice(string $contractCode, float $exVat, array $overrides = []): void
    {
        $incVat = round($exVat * 1.1, 2);
        $collected = (float) ($overrides['total_collected_amount'] ?? 0);
        $deductions = (float) ($overrides['total_deductions'] ?? 0);
        $withheld = (float) ($overrides['total_withhold_amount'] ?? 0);

        DB::table('customer_invoices')->insert($overrides + [
            'company_id' => self::COMPANY,
            'customer_id' => self::CUSTOMER_A,
            'contract_code' => $contractCode,
            'invoice_date' => $this->today,
            'invoice_due_date' => $this->today,
            'currency' => 'EGP',
            'is_canceled' => 0,
            'invoice_amount' => (string) $exVat,
            'net_invoice_amount' => (string) $incVat,
            'total_collected_amount' => $collected,
            'total_deductions' => $deductions,
            'total_withhold_amount' => $withheld,
            'net_balance' => (string) round($incVat - $collected - $withheld - $deductions, 2),
            'invoice_amount_in_main_currency' => (string) $exVat,
            'total_collected_amount_in_main_currency' => $collected,
            'net_balance_in_main_currency' => (string) round($incVat - $collected - $withheld - $deductions, 2),
        ]);
    }

    private function build(?string $asOf = null): array
    {
        // Most tests only care about the as-of end of the period.
        return app(ContractDashboardService::class)
            ->build(Company::findOrFail(self::COMPANY), null, $asOf);
    }

    private function buildPeriod(?string $from, ?string $to): array
    {
        return app(ContractDashboardService::class)
            ->build(Company::findOrFail(self::COMPANY), $from, $to);
    }

    /** An invoice due $days ago, still fully outstanding. */
    private function overdueInvoice(string $contractCode, float $exVat, int $daysOverdue): void
    {
        $this->invoice($contractCode, $exVat, [
            'invoice_due_date' => Carbon::today()->subDays($daysOverdue)->format('Y-m-d'),
            'invoice_date' => Carbon::today()->subDays($daysOverdue + 30)->format('Y-m-d'),
        ]);
    }

    // ---------------------------------------------------------------
    // billing math
    // ---------------------------------------------------------------

    public function test_invoiced_and_remaining_are_the_sums_they_claim_to_be(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200);
        $this->invoice('C-1', 300);

        $this->contract(['code' => 'C-2', 'amount' => 500]);
        $this->invoice('C-2', 100);

        $egp = $this->build()['byCurrency']['EGP'];

        $this->assertSame(1500.0, $egp['value'], '1000 + 500');
        $this->assertSame(600.0, $egp['invoiced'], '200 + 300 + 100');
        $this->assertSame(900.0, $egp['remaining'], '1500 − 600');
        $this->assertSame(40.0, $egp['utilization'], '600 / 1500');
    }

    public function test_remaining_always_equals_value_minus_invoiced(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 250);
        $this->contract(['code' => 'C-2', 'amount' => 700, 'currency' => 'USD']);

        foreach ($this->build()['byCurrency'] as $currency => $kpis) {
            $this->assertEqualsWithDelta(
                $kpis['value'] - $kpis['invoiced'],
                $kpis['remaining'],
                0.001,
                "{$currency}: remaining is not value − invoiced."
            );
        }
    }

    public function test_a_contract_with_no_invoices_is_fully_remaining(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 800]);

        $data = $this->build();

        $this->assertSame(0.0, $data['byCurrency']['EGP']['invoiced']);
        $this->assertSame(800.0, $data['byCurrency']['EGP']['remaining']);
        $this->assertSame(1, $data['counts']['not_invoiced']);
        $this->assertSame(0.0, $data['byCurrency']['EGP']['utilization']);
    }

    // ---------------------------------------------------------------
    // the three bugs this rewrite fixed
    // ---------------------------------------------------------------

    /**
     * An invoice in a different currency from its contract must not be
     * added to that contract's totals. Live data has EGP invoices
     * posted against a USD contract; counting them made one contract
     * report 1,896,506 invoiced against a value of 1,157,328.
     */
    public function test_an_invoice_in_another_currency_does_not_count_towards_the_contract(): void
    {
        $this->contract(['code' => 'C-USD', 'amount' => 1000, 'currency' => 'USD']);
        $this->invoice('C-USD', 100, ['currency' => 'USD']);
        $this->invoice('C-USD', 9999, ['currency' => 'EGP']);   // wrong currency

        $data = $this->build();

        $this->assertSame(100.0, $data['byCurrency']['USD']['invoiced'], 'Only the USD invoice counts.');
        $this->assertSame(900.0, $data['byCurrency']['USD']['remaining']);
        $this->assertSame(1, $data['dataQuality']['mismatched_currency_count'], 'The EGP invoice must be reported, not silently dropped.');
        $this->assertSame('C-USD', $data['dataQuality']['mismatched_currency_invoices'][0]['contract_code']);
    }

    public function test_a_cancelled_invoice_is_not_billed(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200);
        $this->invoice('C-1', 750, ['is_canceled' => 1]);

        $egp = $this->build()['byCurrency']['EGP'];

        $this->assertSame(200.0, $egp['invoiced']);
        $this->assertSame(800.0, $egp['remaining']);
    }

    /**
     * Billing is measured before tax against the contract value;
     * collections are measured on the tax-inclusive figures. Reporting
     * a 200 invoice as "200 invoiced / 220 uncollected" in one row is
     * what made the old page impossible to reconcile.
     */
    public function test_billing_is_before_tax_and_collections_are_after_tax(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200);

        $egp = $this->build()['byCurrency']['EGP'];

        $this->assertSame(200.0, $egp['invoiced'], 'ex-VAT, comparable with the contract value');
        $this->assertSame(220.0, $egp['billed'], '200 × 1.1');
        $this->assertSame(220.0, $egp['uncollected'], 'nothing collected yet');
        $this->assertSame(800.0, $egp['remaining'], '1000 − 200, still on the ex-VAT base');
    }

    // ---------------------------------------------------------------
    // collections reconcile
    // ---------------------------------------------------------------

    public function test_billed_minus_collected_withheld_and_deductions_equals_uncollected(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200, ['total_collected_amount' => 100, 'total_deductions' => 20]);
        $this->invoice('C-1', 300, ['total_collected_amount' => 50]);

        $egp = $this->build()['byCurrency']['EGP'];

        $this->assertSame(550.0, $egp['billed'], '220 + 330');
        $this->assertSame(150.0, $egp['collected'], '100 + 50');
        $this->assertSame(20.0, $egp['deductions']);
        $this->assertSame(380.0, $egp['uncollected'], '(220 − 100 − 20) + (330 − 50)');
        $this->assertSame(0.0, $egp['reconciliation_gap']);
        $this->assertEqualsWithDelta(27.27, $egp['collection_rate'], 0.01, '150 / 550');
    }

    /**
     * Withholding tax is deducted by the customer and paid to the tax
     * authority: not collected by us, but it does clear the receivable.
     *
     * Leaving it out of the identity is what made 10 of 197 live
     * invoices look broken, with an "unexplained" 38,737.50 gap that
     * was withholding tax to the piastre.
     */
    public function test_withholding_tax_clears_the_receivable_without_being_collected(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 10000]);
        // 600,000 ex-VAT → 660,000 billed; customer pays 642,000 and
        // withholds 18,000. Nothing is left outstanding.
        $this->invoice('C-1', 600000, [
            'total_collected_amount' => 642000,
            'total_withhold_amount' => 18000,
        ]);

        $egp = $this->build()['byCurrency']['EGP'];

        $this->assertSame(660000.0, $egp['billed']);
        $this->assertSame(642000.0, $egp['collected']);
        $this->assertSame(18000.0, $egp['withheld']);
        $this->assertSame(0.0, $egp['uncollected'], 'the invoice is fully settled');
        $this->assertSame(0.0, $egp['reconciliation_gap'], 'and it reconciles');
        $this->assertSame(0, $this->build()['dataQuality']['unbalanced_invoice_count'],
            'a withheld invoice is not a broken one');
    }

    /**
     * When the invoice rows themselves do not add up, the gap is
     * published rather than absorbed into a total that silently fails
     * to balance — and the offending invoices are named.
     */
    public function test_an_invoice_that_does_not_add_up_is_reported_not_absorbed(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        // net_balance deliberately 30 lower than billed − collected − deductions
        $this->invoice('C-1', 200, ['total_collected_amount' => 0, 'net_balance' => '190']);

        $data = $this->build();

        $this->assertSame(30.0, $data['byCurrency']['EGP']['reconciliation_gap'], '220 − 0 − 0 − 190');
        $this->assertSame(1, $data['dataQuality']['unbalanced_invoice_count']);
    }

    // ---------------------------------------------------------------
    // status counts
    // ---------------------------------------------------------------

    public function test_the_status_counts_add_up_to_the_total(): void
    {
        $this->contract(['code' => 'R-1', 'amount' => 10]);
        $this->contract(['code' => 'R-2', 'amount' => 10]);
        $this->contract(['code' => 'A-1', 'amount' => 10, 'status' => Contract::RUNNING_AND_AGAINST]);
        $this->contract(['code' => 'F-1', 'amount' => 10, 'status' => Contract::FINISHED]);
        $this->contract(['code' => 'X-1', 'amount' => 10, 'end_date' => Carbon::today()->subDay()->format('Y-m-d')]);

        $counts = $this->build()['counts'];

        $this->assertSame(5, $counts['total']);
        $this->assertSame(2, $counts['running']);
        $this->assertSame(1, $counts['running_and_against']);
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['finished']);
        $this->assertSame(
            $counts['total'],
            $counts['running'] + $counts['running_and_against'] + $counts['expired'] + $counts['finished'],
            'The four status buckets must partition the total exactly.'
        );
    }

    /**
     * A contract still marked running but past its end date is Expired —
     * counting it as Running is what let 41 of 45 live contracts look
     * healthy.
     */
    public function test_an_open_contract_past_its_end_date_counts_as_expired_not_running(): void
    {
        $this->contract(['code' => 'X-1', 'amount' => 10, 'end_date' => Carbon::today()->subDay()->format('Y-m-d')]);

        $data = $this->build();

        $this->assertSame(0, $data['counts']['running']);
        $this->assertSame(1, $data['counts']['expired']);
        $this->assertSame(1, $data['alerts']['past_end_date_count']);
        $this->assertTrue($data['details']['expired'][0]['is_expired']);
        $this->assertSame(__('Expired'), $data['details']['expired'][0]['status_label']);
    }

    public function test_a_contract_ending_today_is_not_expired(): void
    {
        $this->contract(['code' => 'T-1', 'amount' => 10, 'end_date' => $this->today]);

        $data = $this->build();

        $this->assertSame(1, $data['counts']['running']);
        $this->assertSame(0, $data['counts']['expired']);
        $this->assertSame(1, $data['alerts']['ending_soon_count']);
    }

    public function test_over_billing_is_flagged(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);
        $this->invoice('C-1', 150);

        $data = $this->build();

        $this->assertSame(1, $data['counts']['over_billed']);
        $this->assertSame(-50.0, $data['byCurrency']['EGP']['remaining']);
        $this->assertSame('C-1', $data['details']['over_billed'][0]['code']);
    }

    // ---------------------------------------------------------------
    // scope
    // ---------------------------------------------------------------

    public function test_supplier_contracts_and_other_companies_are_excluded(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);
        $this->contract(['code' => 'S-1', 'amount' => 999, 'model_type' => 'Supplier']);
        DB::table('companies')->insert(['id' => 501, 'main_functional_currency' => 'EGP']);
        $this->contract(['code' => 'O-1', 'amount' => 777, 'company_id' => 501]);

        $data = $this->build();

        $this->assertSame(1, $data['counts']['total']);
        $this->assertSame(100.0, $data['byCurrency']['EGP']['value']);
    }

    // ---------------------------------------------------------------
    // top customers
    // ---------------------------------------------------------------

    public function test_top_customers_rank_by_value_and_by_contract_count_independently(): void
    {
        // A: three small contracts (300 total). B: one big one (1000).
        $this->contract(['code' => 'A-1', 'amount' => 100, 'partner_id' => self::CUSTOMER_A]);
        $this->contract(['code' => 'A-2', 'amount' => 100, 'partner_id' => self::CUSTOMER_A]);
        $this->contract(['code' => 'A-3', 'amount' => 100, 'partner_id' => self::CUSTOMER_A]);
        $this->contract(['code' => 'B-1', 'amount' => 1000, 'partner_id' => self::CUSTOMER_B]);

        $data = $this->build();

        $byValue = $data['topByValue']['EGP'];
        $this->assertSame(self::CUSTOMER_B, (int) $byValue[0]['partner_id'], 'B has the most value');
        $this->assertSame(1000.0, $byValue[0]['value']);

        $byCount = $data['topByCount']['EGP'];
        $this->assertSame(self::CUSTOMER_A, (int) $byCount[0]['partner_id'], 'A has the most contracts');
        $this->assertSame(3, $byCount[0]['contract_count']);
        $this->assertSame(300.0, $byCount[0]['value'], '100 × 3');
    }

    public function test_top_customer_totals_equal_the_currency_totals(): void
    {
        $this->contract(['code' => 'A-1', 'amount' => 100, 'partner_id' => self::CUSTOMER_A]);
        $this->invoice('A-1', 40);
        $this->contract(['code' => 'B-1', 'amount' => 250, 'partner_id' => self::CUSTOMER_B]);
        $this->invoice('B-1', 60);

        $data = $this->build();
        $top = collect($data['topByValue']['EGP']);

        $this->assertSame($data['byCurrency']['EGP']['value'], (float) $top->sum('value'));
        $this->assertSame($data['byCurrency']['EGP']['invoiced'], (float) $top->sum('invoiced'));
    }

    // ---------------------------------------------------------------
    // company-wide totals
    // ---------------------------------------------------------------

    public function test_the_main_currency_total_converts_only_what_it_can_trust(): void
    {
        // In the company's own currency — always converts at 1.
        $this->contract(['code' => 'E-1', 'amount' => 1000, 'currency' => 'EGP', 'exchange_rate' => 1]);
        // Foreign with a real rate — converts.
        $this->contract(['code' => 'U-1', 'amount' => 100, 'currency' => 'USD', 'exchange_rate' => 50]);
        // Foreign still on the default rate of 1 — nobody set it, so it is left out.
        $this->contract(['code' => 'U-2', 'amount' => 200, 'currency' => 'USD', 'exchange_rate' => 1]);

        $totals = $this->build()['mainCurrencyTotals'];

        $this->assertSame(6000.0, $totals['value'], '1000 × 1 + 100 × 50, U-2 excluded');
        $this->assertSame(1, $totals['value_unconvertible_count']);
    }

    /**
     * An EGP contract carrying a rate of 50 is a data-entry error, not
     * a conversion — it must not multiply the company total by 50.
     */
    public function test_a_home_currency_contract_converts_at_one_whatever_its_rate_says(): void
    {
        $this->contract(['code' => 'E-1', 'amount' => 1000, 'currency' => 'EGP', 'exchange_rate' => 50]);

        $this->assertSame(1000.0, $this->build()['mainCurrencyTotals']['value']);
    }

    // ---------------------------------------------------------------
    // details back every card
    // ---------------------------------------------------------------

    public function test_each_card_opens_a_detail_list_that_matches_its_number(): void
    {
        $this->contract(['code' => 'R-1', 'amount' => 10]);
        $this->contract(['code' => 'X-1', 'amount' => 10, 'end_date' => Carbon::today()->subDay()->format('Y-m-d')]);
        $this->contract(['code' => 'F-1', 'amount' => 10, 'status' => Contract::FINISHED]);
        $this->invoice('F-1', 5);

        $data = $this->build();

        foreach (['running', 'expired', 'finished', 'not_invoiced', 'over_billed'] as $key) {
            $this->assertCount(
                $data['counts'][$key],
                $data['details'][$key],
                "The {$key} card's number and its detail list disagree."
            );
        }

        $this->assertCount($data['counts']['total'], $data['details']['all']);
        $this->assertCount(
            $data['byCurrency']['EGP']['contract_count'],
            $data['details']['by_currency']['EGP']
        );
    }

    // ---------------------------------------------------------------
    // aging
    // ---------------------------------------------------------------

    /**
     * The bucket boundaries, one day either side of each edge. Off-by-one
     * here is the classic aging bug and it is invisible in a total.
     *
     * @dataProvider agingBoundaryProvider
     */
    public function test_an_invoice_lands_in_the_right_aging_bucket(int $daysOverdue, string $expectedBucket): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $this->overdueInvoice('C-1', 100, $daysOverdue);

        $aging = $this->build()['aging']['EGP'];

        $this->assertSame(1, $aging[$expectedBucket]['invoice_count'],
            "{$daysOverdue} days overdue should sit in {$expectedBucket}.");
        $this->assertSame(110.0, $aging[$expectedBucket]['amount'], 'the VAT-inclusive outstanding balance');

        foreach (array_keys($aging) as $bucket) {
            if (in_array($bucket, [$expectedBucket, 'total', 'overdue_total'], true)) {
                continue;
            }
            $this->assertSame(0, $aging[$bucket]['invoice_count'], "{$bucket} should be empty.");
        }
    }

    public static function agingBoundaryProvider(): array
    {
        return [
            'due today is not overdue' => [0, 'not_due'],
            'one day late' => [1, 'd1_30'],
            'last day of 1-30' => [30, 'd1_30'],
            'first day of 31-60' => [31, 'd31_60'],
            'last day of 31-60' => [60, 'd31_60'],
            'first day of 61-90' => [61, 'd61_90'],
            'last day of 61-90' => [90, 'd61_90'],
            'first day of 90+' => [91, 'd90_plus'],
            'very old' => [800, 'd90_plus'],
        ];
    }

    public function test_an_invoice_due_in_the_future_is_not_overdue(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 100, ['invoice_due_date' => Carbon::today()->addDays(15)->format('Y-m-d')]);

        $aging = $this->build()['aging']['EGP'];

        $this->assertSame(110.0, $aging['not_due']['amount']);
        $this->assertSame(0.0, $aging['overdue_total']['amount']);
    }

    /**
     * Aging is a split of the same money, not a second opinion about
     * it — the buckets have to add back up to Uncollected exactly.
     */
    public function test_the_aging_buckets_add_up_to_uncollected(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $this->overdueInvoice('C-1', 100, 5);
        $this->overdueInvoice('C-1', 200, 45);
        $this->overdueInvoice('C-1', 300, 75);
        $this->overdueInvoice('C-1', 400, 400);
        $this->invoice('C-1', 500, ['invoice_due_date' => Carbon::today()->addDays(10)->format('Y-m-d')]);

        $data = $this->build();
        $aging = $data['aging']['EGP'];

        $bucketSum = 0.0;
        foreach (array_keys($this->bucketKeys()) as $bucket) {
            $bucketSum += $aging[$bucket]['amount'];
        }

        $this->assertEqualsWithDelta(1650.0, $bucketSum, 0.01, '(100+200+300+400+500) × 1.1');
        $this->assertEqualsWithDelta($data['byCurrency']['EGP']['uncollected'], $bucketSum, 0.01);
        $this->assertEqualsWithDelta($aging['total']['amount'], $bucketSum, 0.01);
        $this->assertEqualsWithDelta(1100.0, $aging['overdue_total']['amount'], 0.01, 'everything except the 500 not yet due');
    }

    public function test_aging_ignores_invoices_in_another_currency(): void
    {
        $this->contract(['code' => 'C-USD', 'amount' => 10000, 'currency' => 'USD']);
        $this->invoice('C-USD', 100, ['currency' => 'USD', 'invoice_due_date' => Carbon::today()->subDays(45)->format('Y-m-d')]);
        $this->invoice('C-USD', 9999, ['currency' => 'EGP', 'invoice_due_date' => Carbon::today()->subDays(45)->format('Y-m-d')]);

        $aging = $this->build()['aging']['USD'];

        $this->assertSame(1, $aging['d31_60']['invoice_count']);
        $this->assertSame(110.0, $aging['d31_60']['amount']);
    }

    // ---------------------------------------------------------------
    // trend
    // ---------------------------------------------------------------

    public function test_the_trend_lists_every_month_in_the_window_even_the_empty_ones(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 10000]);

        $from = Carbon::today()->subMonthsNoOverflow(3)->startOfMonth();
        $to = Carbon::today();
        $trend = $this->buildPeriod($from->format('Y-m-d'), $to->format('Y-m-d'))['trend']['EGP'];

        $this->assertCount(4, $trend, 'four calendar months are spanned');
        $this->assertSame($from->format('Y-m'), $trend[0]['month']);
        $this->assertSame($to->format('Y-m'), end($trend)['month']);
        $this->assertSame([0, 0, 0, 0], array_column($trend, 'invoice_count'), 'empty months are still listed');
    }

    public function test_the_trend_puts_each_invoice_in_its_own_month(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $thisMonth = Carbon::today()->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonthNoOverflow();

        $this->invoice('C-1', 100, ['invoice_date' => $thisMonth->format('Y-m-d'), 'total_collected_amount' => 40]);
        $this->invoice('C-1', 250, ['invoice_date' => $lastMonth->format('Y-m-d'), 'total_collected_amount' => 10]);

        $trend = collect($this->build()['trend']['EGP'])->keyBy('month');

        $this->assertSame(100.0, $trend[$thisMonth->format('Y-m')]['invoiced']);
        $this->assertSame(40.0, $trend[$thisMonth->format('Y-m')]['collected']);
        $this->assertSame(250.0, $trend[$lastMonth->format('Y-m')]['invoiced']);
        $this->assertSame(10.0, $trend[$lastMonth->format('Y-m')]['collected']);
    }

    public function test_the_trend_total_matches_the_invoiced_total_when_everything_is_inside_the_window(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $this->invoice('C-1', 100, ['invoice_date' => Carbon::today()->format('Y-m-d')]);
        $this->invoice('C-1', 200, ['invoice_date' => Carbon::today()->subMonthsNoOverflow(2)->format('Y-m-d')]);

        $data = $this->build();

        $this->assertSame(
            $data['byCurrency']['EGP']['invoiced'],
            (float) collect($data['trend']['EGP'])->sum('invoiced')
        );
    }

    // ---------------------------------------------------------------
    // the as-of date
    // ---------------------------------------------------------------

    public function test_an_invoice_raised_after_the_as_of_date_has_not_happened_yet(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200, ['invoice_date' => Carbon::today()->subDays(10)->format('Y-m-d')]);
        $this->invoice('C-1', 500, ['invoice_date' => Carbon::today()->format('Y-m-d')]);

        $asOf = Carbon::today()->subDays(5)->format('Y-m-d');
        $egp = $this->build($asOf)['byCurrency']['EGP'];

        $this->assertSame(200.0, $egp['invoiced'], 'only the older invoice existed then');
        $this->assertSame(800.0, $egp['remaining']);
    }

    public function test_expiry_is_judged_against_the_as_of_date(): void
    {
        $endDate = Carbon::today()->subDays(10)->format('Y-m-d');
        $this->contract(['code' => 'C-1', 'amount' => 100, 'end_date' => $endDate]);

        $this->assertSame(1, $this->build()['counts']['expired'], 'expired as of today');
        $this->assertSame(
            0,
            $this->build(Carbon::today()->subDays(20)->format('Y-m-d'))['counts']['expired'],
            'it had not expired 20 days ago'
        );
    }

    public function test_aging_is_measured_from_the_as_of_date(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $this->overdueInvoice('C-1', 100, 45);

        $this->assertSame(1, $this->build()['aging']['EGP']['d31_60']['invoice_count']);

        // Rewind 20 days and the same invoice is only 25 days overdue.
        $asOf = Carbon::today()->subDays(20)->format('Y-m-d');
        $this->assertSame(1, $this->build($asOf)['aging']['EGP']['d1_30']['invoice_count']);
    }

    public function test_the_dates_accept_the_date_picker_format(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);
        $from = Carbon::today()->subMonthsNoOverflow(2);
        $to = Carbon::today()->subDays(3);

        $data = $this->buildPeriod($from->format('d/m/Y'), $to->format('d/m/Y'));

        $this->assertSame($from->format('Y-m-d'), $data['startDate']);
        $this->assertSame($to->format('Y-m-d'), $data['endDate']);
    }

    public function test_nonsense_dates_fall_back_to_the_default_period_instead_of_failing(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);

        foreach (['not-a-date', '', null] as $bad) {
            $data = $this->buildPeriod($bad, $bad);

            $this->assertSame($this->today, $data['endDate']);
            $this->assertSame(
                Carbon::today()->subYearsNoOverflow($data['defaultPeriodYears'])->format('Y-m-d'),
                $data['startDate']
            );
            $this->assertTrue($data['isDefaultPeriod']);
        }
    }

    // ---------------------------------------------------------------
    // the period
    // ---------------------------------------------------------------

    public function test_the_period_defaults_to_the_last_two_years_ending_today(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);

        $data = $this->build();

        $this->assertSame(2, $data['defaultPeriodYears']);
        $this->assertSame($this->today, $data['endDate']);
        $this->assertSame(Carbon::today()->subYearsNoOverflow(2)->format('Y-m-d'), $data['startDate']);
        $this->assertTrue($data['isDefaultPeriod']);
    }

    /**
     * Position figures must stay cumulative. Narrowing "invoiced" to the
     * period would make "remaining to invoice" wrong, because invoices
     * raised before the period would silently reappear as unbilled.
     */
    public function test_the_start_date_narrows_activity_but_never_the_cumulative_position(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 1000]);
        $this->invoice('C-1', 200, ['invoice_date' => Carbon::today()->subMonthsNoOverflow(10)->format('Y-m-d')]);
        $this->invoice('C-1', 300, ['invoice_date' => Carbon::today()->format('Y-m-d')]);

        $from = Carbon::today()->subMonthsNoOverflow(1)->format('Y-m-d');
        $data = $this->buildPeriod($from, $this->today);

        $this->assertSame(500.0, $data['byCurrency']['EGP']['invoiced'], 'cumulative: both invoices');
        $this->assertSame(500.0, $data['byCurrency']['EGP']['value'] - $data['byCurrency']['EGP']['remaining']);
        $this->assertSame(300.0, $data['period']['EGP']['invoiced'], 'in period: only the recent one');
        $this->assertSame(1, $data['period']['EGP']['invoice_count']);
    }

    public function test_the_period_totals_collected_and_withheld_too(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100000]);
        $this->invoice('C-1', 1000, [
            'invoice_date' => Carbon::today()->format('Y-m-d'),
            'total_collected_amount' => 700,
            'total_withhold_amount' => 30,
        ]);

        $period = $this->build()['period']['EGP'];

        $this->assertSame(1000.0, $period['invoiced']);
        $this->assertSame(700.0, $period['collected']);
        $this->assertSame(30.0, $period['withheld']);
    }

    /**
     * Someone typing the dates the wrong way round wants a report, not
     * an empty page.
     */
    public function test_a_start_after_the_end_is_swapped_rather_than_returning_nothing(): void
    {
        $this->contract(['code' => 'C-1', 'amount' => 100]);
        $early = Carbon::today()->subMonthsNoOverflow(3)->format('Y-m-d');
        $late = $this->today;

        $data = $this->buildPeriod($late, $early);

        $this->assertSame($early, $data['startDate']);
        $this->assertSame($late, $data['endDate']);
    }

    /**
     * @return array<string, mixed>
     */
    private function bucketKeys(): array
    {
        return ['not_due' => 1, 'd1_30' => 1, 'd31_60' => 1, 'd61_90' => 1, 'd90_plus' => 1];
    }

    public function test_an_empty_company_produces_zeroes_not_errors(): void
    {
        $data = $this->build();

        $this->assertSame(0, $data['counts']['total']);
        $this->assertSame([], $data['currencies']);
        $this->assertSame([], $data['byCurrency']);
        $this->assertSame(0.0, $data['mainCurrencyTotals']['value']);
        $this->assertSame(0, $data['dataQuality']['mismatched_currency_count']);
        $this->assertSame([], $data['aging']);
        $this->assertSame([], $data['trend']);
    }
}

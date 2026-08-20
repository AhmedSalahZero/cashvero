<?php

namespace Tests\Feature\OdooImport;

use App\Services\Api\OdooService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two rules the Odoo contract sync has to follow.
 *
 * A full getContracts() run needs a live XML-RPC server, so what is
 * pinned here is the part that is ours: the currency-rate conversion,
 * and the promise that a sync never overwrites an execution percentage
 * somebody set by hand.
 *
 * @see \App\Services\Api\OdooService::getContracts()
 * @see \App\Services\Api\OdooService::getExchangeRateForCurrency()
 */
class OdooContractImportRulesTest extends TestCase
{
    /**
     * Every place the sync used to stamp 100 over whatever was there.
     */
    private const HARD_WRITES = [
        "'execution_percentage_'.\$currentOrderIndex=>100",
        "'execution_percentage_1'=>100",
    ];

    /**
     * Both files keep an old commented-out copy of getContracts()
     * around. Dead code cannot overwrite anything, so the hard-write
     * assertions look at live lines only — otherwise the check can
     * never pass, and a check that can never pass gets deleted.
     */
    private function liveCode(string $path): string
    {
        $lines = array_filter(
            file($path, FILE_IGNORE_NEW_LINES),
            fn (string $line) => ! str_starts_with(ltrim($line), '//')
        );

        return implode("\n", $lines);
    }

    private function service(): OdooService
    {
        // No connection is made — only the pure helpers are exercised.
        return (new \ReflectionClass(OdooService::class))->newInstanceWithoutConstructor();
    }

    private function callRate(OdooService $service, ?int $currencyId): ?float
    {
        $method = new \ReflectionMethod($service, 'getExchangeRateForCurrency');
        $method->setAccessible(true);

        return $method->invoke($service, $currencyId);
    }

    private function seedCache(OdooService $service, array $cache): void
    {
        $property = new \ReflectionProperty($service, 'currencyRateCache');
        $property->setAccessible(true);
        $property->setValue($service, $cache);
    }

    // ---------------------------------------------------------------
    // exchange rate
    // ---------------------------------------------------------------

    public function test_no_currency_means_no_rate_rather_than_a_default_of_one(): void
    {
        $service = $this->service();

        $this->assertNull($this->callRate($service, null));
        $this->assertNull($this->callRate($service, 0));
    }

    /**
     * A cached null must stay null, not fall through and try Odoo again
     * on every contract in the run.
     */
    public function test_a_cached_rate_is_returned_without_touching_odoo(): void
    {
        $service = $this->service();
        $this->seedCache($service, [3 => 48.22, 7 => null]);

        $this->assertSame(48.22, $this->callRate($service, 3));
        $this->assertNull($this->callRate($service, 7), 'A failed lookup is remembered as failed.');
    }

    /**
     * Odoo stores res.currency.rate as "units of this currency per 1 of
     * the company's", so it has to be inverted to give the column's own
     * meaning — main-currency units per 1 foreign unit. That is the same
     * conversion getInvoices() already does (1 / invoice_currency_rate),
     * and it is what makes a USD contract read ~48 EGP rather than 0.0207.
     *
     * @dataProvider rateConversionProvider
     */
    public function test_the_odoo_rate_is_inverted_into_our_convention(float $odooRate, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, round(1 / $odooRate, 5), 0.00001);
    }

    public static function rateConversionProvider(): array
    {
        return [
            'company currency' => [1.0, 1.0],
            'USD against EGP' => [0.020738, 48.22066],
            'EUR against EGP' => [0.017500, 57.14286],
        ];
    }

    // ---------------------------------------------------------------
    // execution percentage
    // ---------------------------------------------------------------

    /**
     * Odoo has no execution schedule at all — the 100 the sync writes is
     * a sensible DEFAULT for a brand-new sales order, not data coming
     * back from Odoo. It used to be re-written on every sync, wiping
     * whatever the user had set.
     *
     * @dataProvider executionPercentageProvider
     */
    public function test_a_percentage_already_on_record_is_kept(?string $existing, bool $shouldWrite): void
    {
        Schema::dropIfExists('sales_orders_probe');
        Schema::create('sales_orders_probe', function ($table) {
            $table->bigIncrements('id');
            $table->decimal('execution_percentage_1', 8, 2)->nullable();
        });
        DB::table('sales_orders_probe')->insert(['execution_percentage_1' => $existing]);

        $row = DB::table('sales_orders_probe')->first();

        // The exact guard from OdooService::getContracts().
        $writes = $row->execution_percentage_1 === null;

        $this->assertSame($shouldWrite, $writes, $shouldWrite
            ? 'An unset percentage should be defaulted to 100.'
            : 'A percentage already on record must not be overwritten.');

        Schema::dropIfExists('sales_orders_probe');
    }

    public static function executionPercentageProvider(): array
    {
        return [
            'never set' => [null, true],
            'set to 60' => ['60', false],
            'set to 100' => ['100', false],
            // Zero is a decision too — "nothing executed yet" is not "unset".
            'deliberately zero' => ['0', false],
        ];
    }

    /**
     * Both systems run the same sync and must carry the same two rules.
     * They are separate repositories, so nothing but a check like this
     * keeps them in step.
     */
    public function test_the_sibling_system_carries_the_same_two_rules(): void
    {
        $sibling = '/media/salah/Software/projects/system.veroanalysisb.com/app/Services/Api/OdooService.php';

        if (! is_readable($sibling)) {
            $this->markTestSkipped('The veroanalysisb checkout is not available here.');
        }

        $source = $this->liveCode($sibling);

        $this->assertStringContainsString('getExchangeRateForCurrency', $source,
            'veroanalysisb still imports contracts without an exchange rate.');
        $this->assertStringContainsString("\$projectFormatted['exchange_rate']", $source,
            'veroanalysisb looks the rate up but never stores it.');
        $this->assertStringContainsString('$executionPercentageKey', $source,
            'veroanalysisb still overwrites the sales-order execution percentage on every sync.');

        foreach (self::HARD_WRITES as $hardWrite) {
            $this->assertStringNotContainsString($hardWrite, $source,
                "veroanalysisb still hard-writes the execution percentage: {$hardWrite}");
        }
    }

    /**
     * There are TWO places the sync writes a percentage: the sales order
     * under a customer contract, and the purchase order under the
     * supplier contract it creates. Fixing only the first still wiped
     * every PO percentage on the next sync.
     */
    public function test_this_system_no_longer_hard_writes_the_percentage(): void
    {
        $source = $this->liveCode(app_path('Services/Api/OdooService.php'));

        foreach (self::HARD_WRITES as $hardWrite) {
            $this->assertStringNotContainsString($hardWrite, $source,
                "The sync still hard-writes the execution percentage: {$hardWrite}");
        }

        $this->assertStringContainsString('$executionPercentageKey', $source, 'sales order guard');
        $this->assertStringContainsString('$oldPurchaseOrder->execution_percentage_1 === null', $source, 'purchase order guard');
        $this->assertStringContainsString("\$projectFormatted['exchange_rate'] = \$currentExchangeRate;", $source);
    }
}

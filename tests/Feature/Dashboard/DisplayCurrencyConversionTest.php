<?php

namespace Tests\Feature\Dashboard;

use App\Models\ForeignExchangeRate;
use Tests\TestCase;

/**
 * The Cash Flow Projection is shown one tab per currency. The main
 * functional currency tab gathers EVERY currency, each converted; a
 * specific foreign-currency tab filters down to that currency and must
 * show its figures untouched.
 *
 * The forecast rows ignored that second half: they called
 * getExchangeRateAtOrOne(contractCurrency -> mainFunctionalCurrency)
 * unconditionally. On company 148's EURO tab that turned EUR 605,739.75
 * into 35,612,954.25 — the EGP equivalent — and stacked it in the same
 * column as "Cash & Banks Balance", which was correctly left in EURO.
 * One column, two currencies, summed together.
 *
 * @see \App\Models\ForeignExchangeRate::getExchangeRateForDisplayCurrency()
 */
class DisplayCurrencyConversionTest extends TestCase
{
    private const RATE_DATE = '2026-08-23';

    /**
     * An in-memory rate table so the assertions do not depend on the
     * test database carrying company 148's real rates. Company id is
     * unique per test because getExchangeRateAt() memoises statically.
     */
    private function rates(int $companyId, float $rate = 58.7925)
    {
        return collect([
            new ForeignExchangeRate([
                'company_id' => $companyId,
                'from_currency' => 'EURO',
                'to_currency' => 'EGP',
                'date' => '2026-01-01',
                'exchange_rate' => $rate,
            ]),
        ]);
    }

    // ---------------------------------------------------------------
    // the rule
    // ---------------------------------------------------------------

    /**
     * The rate table deliberately DOES carry a EURO -> EGP rate here.
     * With an empty table the lookup returns 1 anyway and the test would
     * pass whether or not the guard exists — it has to be able to fail.
     */
    public function test_no_conversion_when_the_amount_is_already_in_the_displayed_currency(): void
    {
        $companyId = 9300;
        $rates = $this->rates($companyId);

        $this->assertEqualsWithDelta(
            58.7925,
            ForeignExchangeRate::getExchangeRateAtOrOne('EURO', 'EGP', self::RATE_DATE, $companyId, $rates),
            0.0001,
            'Precondition: without the guard this is the rate that would be applied.'
        );

        $this->assertSame(
            1,
            ForeignExchangeRate::getExchangeRateForDisplayCurrency('EURO', 'EURO', 'EGP', self::RATE_DATE, $companyId, $rates),
            'A EURO contract on the EURO tab must be shown in EURO, not multiplied by the EUR/EGP rate.'
        );
    }

    public function test_conversion_still_happens_on_the_main_currency_tab(): void
    {
        $rates = $this->rates(9148);

        $this->assertEqualsWithDelta(
            58.7925,
            ForeignExchangeRate::getExchangeRateForDisplayCurrency('EURO', 'EGP', 'EGP', self::RATE_DATE, 9148, $rates),
            0.0001,
            'The main currency tab aggregates every currency, so a EURO contract must still be converted there.'
        );
    }

    public function test_a_missing_display_currency_falls_back_to_the_old_behaviour(): void
    {
        foreach ([null, ''] as $index => $noDisplayCurrency) {
            $companyId = 9200 + $index;
            $rates = $this->rates($companyId);

            $this->assertSame(
                ForeignExchangeRate::getExchangeRateAtOrOne('EURO', 'EGP', self::RATE_DATE, $companyId, $rates),
                ForeignExchangeRate::getExchangeRateForDisplayCurrency('EURO', $noDisplayCurrency, 'EGP', self::RATE_DATE, $companyId, $rates),
                'Callers that never pass a tab currency must keep converting exactly as before.'
            );
        }
    }

    public function test_the_main_currency_on_its_own_tab_is_never_scaled(): void
    {
        $this->assertSame(
            1,
            ForeignExchangeRate::getExchangeRateForDisplayCurrency('EGP', 'EGP', 'EGP', self::RATE_DATE, 9400, $this->rates(9400)),
            'EGP shown on the EGP tab is already in the right currency.'
        );
    }

    // ---------------------------------------------------------------
    // both forecast rows go through it
    // ---------------------------------------------------------------

    public function test_the_customer_forecast_uses_the_display_currency_rate(): void
    {
        $trait = file_get_contents(app_path('Traits/Models/HasForecastedProjectCollection.php'));

        $this->assertStringContainsString(
            'ForeignExchangeRate::getExchangeRateForDisplayCurrency($contract->getCurrency(), $currency,',
            $trait,
            'Forecasted Project Collection must respect the tab currency.'
        );
        $this->assertStringNotContainsString(
            'ForeignExchangeRate::getExchangeRateAtOrOne($contract->getCurrency()',
            $trait,
            'The unconditional conversion is the bug; it must not come back.'
        );
    }

    public function test_the_supplier_forecast_uses_the_display_currency_rate(): void
    {
        $model = file_get_contents(app_path('Models/SupplierInvoice.php'));

        $this->assertStringContainsString(
            'ForeignExchangeRate::getExchangeRateForDisplayCurrency($contract->getCurrency(),$currency,',
            $model,
            'Forecasted Project Payment has the same bug and the same fix.'
        );
        $this->assertStringNotContainsString(
            'ForeignExchangeRate::getExchangeRateAtOrOne($contract->getCurrency()',
            $model
        );
    }

    /**
     * Every remaining aggregator on the report already guarded its
     * conversion with $showAllCurrenciesConverted. If a new one appears
     * that converts a contract's currency without asking which tab is
     * being drawn, it is the same bug again.
     */
    public function test_no_aggregator_converts_a_contract_currency_unconditionally(): void
    {
        $offenders = [];
        foreach (['Models', 'Traits', 'Http/Controllers'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path($dir)));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $body = file_get_contents($file->getPathname());
                if (str_contains($body, 'getExchangeRateAtOrOne($contract->getCurrency()')) {
                    $offenders[] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $offenders, 'These convert to the main currency no matter which currency tab is shown.');
    }
}

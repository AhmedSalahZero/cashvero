<?php

namespace Tests\Feature\Dashboard;

use App\Models\ForeignExchangeRate;
use Tests\TestCase;

/**
 * The Cash Flow Projection is shown one tab per currency, and every row
 * of a tab is in the MAIN functional currency — including a foreign
 * currency tab. On company 92's USD tab "Cash & Banks Balance" lists
 * every account (EGP ones too) as an EGP equivalent, 19,943,832.77, in
 * both cashvero and system.veroanalysisb.com.
 *
 * For a while the two forecast rows ("Forecasted Project Collection",
 * "Forecasted Suppliers Contract Payments") were the exception: through
 * ForeignExchangeRate::getExchangeRateForDisplayCurrency() they stayed in
 * the contract's own currency on that contract's tab — USD 17,801,667
 * next to EGP equivalents in the same column. On 2026-11-11 the client
 * confirmed system.veroanalysisb.com is the reference: forecast rows are
 * converted to the main currency like the rest of the tab (884,816,173
 * on company 92's USD tab). These tests keep it that way.
 */
class DisplayCurrencyConversionTest extends TestCase
{
    private const RATE_DATE = '2026-08-23';

    /**
     * An in-memory rate table so the assertions do not depend on the test
     * database's rates. Company id is unique per test because
     * getExchangeRateAt() memoises statically.
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

    public function test_a_contract_amount_is_converted_to_the_main_currency(): void
    {
        $this->assertEqualsWithDelta(
            58.7925,
            ForeignExchangeRate::getExchangeRateAtOrOne('EURO', 'EGP', self::RATE_DATE, 9300, $this->rates(9300)),
            0.0001,
            'A EURO contract is shown as its EGP equivalent on every tab, its own EURO tab included.'
        );
    }

    public function test_the_main_currency_is_never_scaled(): void
    {
        $this->assertEquals(
            1,
            ForeignExchangeRate::getExchangeRateAtOrOne('EGP', 'EGP', self::RATE_DATE, 9400, $this->rates(9400)),
            'EGP is already in the main currency.'
        );
    }

    /**
     * Both forecast rows go through the shared calculation, and that
     * calculation converts every phase (and every monthly slice) to the
     * main currency — never "as is" because the tab happens to match.
     */
    public function test_the_forecast_rows_always_convert_to_the_main_currency(): void
    {
        $trait = file_get_contents(app_path('Traits/Models/HasForecastedProjectCollection.php'));

        $this->assertStringContainsString(
            'ForeignExchangeRate::getExchangeRateAtOrOne($contract->getCurrency(), $mainFunctionalCurrency,',
            $trait,
            'Each phase converts to the main functional currency at its own collection date.'
        );
        $this->assertStringContainsString(
            "'ifnull('.\$invoiceSettledMainColumn.', 0)'",
            $trait,
            'The deduction is read in the main currency (the *_in_main_currency columns), as on the source system.'
        );

        $model = file_get_contents(app_path('Models/SupplierInvoice.php'));
        $this->assertStringContainsString(
            'computeForecastedProjectCollection(',
            $model,
            'Forecasted Project Payment must go through the shared calculation.'
        );
    }

    public function test_the_keep_it_in_the_contract_currency_rule_is_gone(): void
    {
        $this->assertFalse(
            method_exists(ForeignExchangeRate::class, 'getExchangeRateForDisplayCurrency'),
            'Forecast rows on a foreign-currency tab are EGP equivalents (decided 2026-11-11).'
        );
    }
}

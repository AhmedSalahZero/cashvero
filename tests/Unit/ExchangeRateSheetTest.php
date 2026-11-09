<?php

namespace Tests\Unit;

use App\Models\ForeignExchangeRate;
use Tests\Fixtures\Fixtures;
use Tests\TestCase;

/**
 * Which exchange rate applies on a date — checked against tests/Fixtures/fx_cases.json.
 * The rates are handed in as a list (as every report does after loading them once), so nothing
 * here needs the database.
 *
 * Run: php artisan test --filter=ExchangeRateSheetTest
 */
class ExchangeRateSheetTest extends TestCase
{
    private function rates(): \Illuminate\Support\Collection
    {
        return collect(Fixtures::load('fx_cases')['rates'])->map(fn (array $row) => new ForeignExchangeRate($row));
    }

    public function test_every_date_gets_the_rate_the_sheet_says(): void
    {
        $rates = $this->rates();

        foreach (Fixtures::load('fx_cases')['cases'] as $case) {
            $this->assertEquals(
                $case['expect'],
                (float) ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($case['from'], $case['to'], $case['date'], $case['company_id'], $rates),
                $case['name'],
            );
        }
    }

    public function test_the_same_currency_is_always_one_to_one(): void
    {
        $this->assertSame(1, ForeignExchangeRate::getExchangeRateAt('EGP', 'EGP', '2026-11-08', 1, $this->rates()));
    }

    public function test_the_request_memo_gives_the_same_answer_as_asking_afresh(): void
    {
        $rates = $this->rates();

        foreach (Fixtures::load('fx_cases')['cases'] as $case) {
            if ($case['from'] === $case['to']) {
                continue;
            }

            $first = ForeignExchangeRate::getExchangeRateAt($case['from'], $case['to'], $case['date'], $case['company_id'], $rates);
            $again = ForeignExchangeRate::getExchangeRateAt($case['from'], $case['to'], $case['date'], $case['company_id'], $rates);

            $this->assertEquals($case['expect'], (float) $first, $case['name']);
            $this->assertEquals($first, $again, $case['name'].' (second time)');
        }
    }

    /** The memo must not hand one company's rate to another company on the same day. */
    public function test_the_memo_keeps_companies_apart(): void
    {
        $rates = $this->rates();

        $this->assertEquals(50, (float) ForeignExchangeRate::getExchangeRateAt('USD', 'EGP', '2026-11-08', 1, $rates));
        $this->assertEquals(99, (float) ForeignExchangeRate::getExchangeRateAt('USD', 'EGP', '2026-11-08', 2, $rates));
    }
}

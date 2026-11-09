<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\Fixtures;
use Tests\TestCase;

/**
 * The answer sheets the server and the screen share (tests/Fixtures, also read by tests/js).
 * If one side changes how it writes money or which dates it accepts, one of the two suites fails.
 *
 * Run: php artisan test --filter=SharedSheetsTest   (and npm test for the screen side)
 */
class SharedSheetsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** The way controllers and print sheets write an amount: number_format($value, 2). */
    public function test_the_server_writes_money_exactly_as_the_sheet_says(): void
    {
        foreach (Fixtures::load('number_format_cases')['cases'] as $case) {
            $this->assertSame($case['expect'], number_format((float) ($case['value'] ?? 0), 2), $case['name']);
        }
    }

    /** The rule every money form and report uses for "no date after today". */
    public function test_the_server_refuses_exactly_the_dates_the_screen_moves_back_to_today(): void
    {
        $sheet = Fixtures::load('date_clamp_cases');
        Carbon::setTestNow($sheet['today'].' 12:00:00');

        foreach ($sheet['cases'] as $case) {
            if ($case['date'] === '') {
                continue;
            }

            $accepted = Validator::make(['date' => $case['date']], ['date' => 'date|before_or_equal:today'])->passes();

            // The screen keeps a date it accepts and moves a refused one back to today.
            $this->assertSame($case['expect'] === $case['date'], $accepted, $case['name']);
        }
    }
}

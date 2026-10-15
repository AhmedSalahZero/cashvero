<?php

namespace Tests\Feature\Contracts;

use App\Support\Contracts\MonthlyExecutionSchedule;
use Tests\TestCase;

/**
 * A normal contract hangs off Sales/Purchase Orders and drops its whole
 * remaining balance on ONE date — the last execution phase's end date
 * plus its collection days. A contract worth 1,000,000 with 400,000
 * executed shows the other 600,000 as a single lump ~90 days out.
 *
 * A monthly-executed contract spreads that remainder evenly across the
 * months of its own period instead. The project owner's example:
 * 1,200,000 over a year, 600,000 remaining in month 6, becomes
 * 100,000 in each of months 7 through 12.
 *
 * @see \App\Support\Contracts\MonthlyExecutionSchedule
 */
class MonthlyExecutionScheduleTest extends TestCase
{
    private const START = '2026-01-01';
    private const END = '2026-12-31';

    // ---------------------------------------------------------------
    // the owner's worked example
    // ---------------------------------------------------------------

    public function test_the_worked_example_lands_one_hundred_thousand_in_each_of_six_months(): void
    {
        $slices = MonthlyExecutionSchedule::forContract(600000, self::START, self::END, '2026-06-15');

        $this->assertSame([
            '2026-07-31' => 100000.0,
            '2026-08-31' => 100000.0,
            '2026-09-30' => 100000.0,
            '2026-10-31' => 100000.0,
            '2026-11-30' => 100000.0,
            '2026-12-31' => 100000.0,
        ], $slices);
    }

    public function test_the_slices_add_back_up_to_the_remaining_balance(): void
    {
        $slices = MonthlyExecutionSchedule::forContract(600000, self::START, self::END, '2026-06-15');

        $this->assertEqualsWithDelta(600000.0, array_sum($slices), 0.001);
    }

    // ---------------------------------------------------------------
    // which months get a slice
    // ---------------------------------------------------------------

    /**
     * The month the report is run in is already under way — its
     * execution is happening now and its invoice arrives through the
     * normal invoice flow, so it gets no forecast slice. This is the
     * rule the owner's example implies (a month-6 report starts at
     * month 7); it is deliberately the one knob to turn if that reading
     * is wrong.
     */
    public function test_the_current_month_gets_no_slice(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds(self::START, self::END, '2026-06-15');

        $this->assertNotContains('2026-06-30', $months);
        $this->assertSame('2026-07-31', $months[0]);
    }

    public function test_the_last_day_of_the_current_month_still_gets_no_slice(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds(self::START, self::END, '2026-06-30');

        $this->assertSame('2026-07-31', $months[0], 'Being on the 30th does not make June a future month.');
    }

    public function test_a_contract_that_has_not_started_is_scheduled_from_its_own_first_month(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds('2026-10-01', '2027-09-30', '2026-06-15');

        $this->assertCount(12, $months, 'None of its months have started, so all twelve are still ahead.');
        $this->assertSame('2026-10-31', $months[0]);
        $this->assertSame('2027-09-30', end($months));
    }

    public function test_a_finished_contract_is_scheduled_for_nothing(): void
    {
        $this->assertSame([], MonthlyExecutionSchedule::remainingMonthEnds('2025-01-01', '2025-12-31', '2026-06-15'));
        $this->assertSame([], MonthlyExecutionSchedule::forContract(500000, '2025-01-01', '2025-12-31', '2026-06-15'));
    }

    public function test_the_final_month_is_cut_at_the_contract_end_date(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds(self::START, '2026-09-10', '2026-06-15');

        $this->assertSame(['2026-07-31', '2026-08-31', '2026-09-10'], $months);
    }

    /**
     * endOfMonth() mutates the Carbon instance it is called on. Walking
     * the period with a shared cursor and no copy() would corrupt the
     * cursor on the first iteration and produce the same month forever.
     */
    public function test_walking_the_period_does_not_repeat_or_skip_a_month(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds('2026-01-01', '2027-12-31', '2025-12-31');

        $this->assertCount(24, $months);
        $this->assertSame($months, array_values(array_unique($months)), 'A mutated cursor shows up as duplicates.');
        $this->assertSame('2026-01-31', $months[0]);
        $this->assertSame('2027-12-31', end($months));
    }

    public function test_february_and_thirty_day_months_get_their_real_last_day(): void
    {
        $months = MonthlyExecutionSchedule::remainingMonthEnds('2028-01-01', '2028-06-30', '2027-12-15');

        $this->assertSame(
            ['2028-01-31', '2028-02-29', '2028-03-31', '2028-04-30', '2028-05-31', '2028-06-30'],
            $months,
            '2028 is a leap year; a naive +30 days would drift.'
        );
    }

    // ---------------------------------------------------------------
    // the split itself
    // ---------------------------------------------------------------

    public function test_rounding_left_over_is_carried_into_the_last_slice(): void
    {
        $slices = MonthlyExecutionSchedule::forContract(1000, '2026-01-01', '2026-09-30', '2026-06-15');

        $this->assertSame([
            '2026-07-31' => 333.33,
            '2026-08-31' => 333.33,
            '2026-09-30' => 333.34,
        ], $slices, 'Rounding every slice the same way would make the row total drift from the contract.');
        $this->assertEqualsWithDelta(1000.0, array_sum($slices), 0.001);
    }

    public function test_nothing_is_scheduled_when_there_is_nothing_left(): void
    {
        $this->assertSame([], MonthlyExecutionSchedule::forContract(0, self::START, self::END, '2026-06-15'));
        $this->assertSame([], MonthlyExecutionSchedule::forContract(-5000, self::START, self::END, '2026-06-15'));
    }

    public function test_a_single_remaining_month_takes_the_whole_balance(): void
    {
        $slices = MonthlyExecutionSchedule::forContract(100000, self::START, '2026-07-31', '2026-06-15');

        $this->assertSame(['2026-07-31' => 100000.0], $slices);
    }

    /**
     * The split is remaining / remaining-months, NOT
     * contract-value / total-months. A contract behind on its invoicing
     * has to catch up over the months it has left, which is the whole
     * point of spreading the REMAINDER rather than a fixed instalment.
     */
    public function test_a_contract_behind_on_invoicing_catches_up_over_what_is_left(): void
    {
        // 1,200,000 contract, only 400,000 invoiced by month 6 -> 800,000 left over 6 months
        $slices = MonthlyExecutionSchedule::forContract(800000, self::START, self::END, '2026-06-15');

        $this->assertCount(6, $slices);
        foreach ($slices as $amount) {
            $this->assertEqualsWithDelta(133333.34, $amount, 0.02, "Not the 100,000 nominal instalment.");
        }
    }
}

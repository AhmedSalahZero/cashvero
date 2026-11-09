<?php

namespace Tests\Unit;

use App\Models\LoanScheduleSettlement;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Fixtures;

/**
 * Splitting a loan installment payment into interest and principle — interest first.
 * Checked against the hand-worked sheet tests/Fixtures/loan_split_cases.json, then against the
 * two things that must hold for ANY amounts.
 *
 * Run: php artisan test --filter=LoanSplitSheetTest
 */
class LoanSplitSheetTest extends TestCase
{
    public function test_every_payment_splits_the_way_the_sheet_says(): void
    {
        foreach (Fixtures::load('loan_split_cases')['cases'] as $case) {
            $interest = LoanScheduleSettlement::interestPaidFor($case['paid'], $case['interest']);
            $principle = LoanScheduleSettlement::principlePaidFor($case['paid'], $case['interest'], $case['principle']);

            $this->assertEqualsWithDelta($case['expect']['interest_paid'], $interest, 0.001, "{$case['name']} — interest");
            $this->assertEqualsWithDelta($case['expect']['principle_paid'], $principle, 0.001, "{$case['name']} — principle");
        }
    }

    /** Whatever the numbers: the two parts add up to what was paid, up to the installment. */
    public function test_the_two_parts_always_add_up_to_what_was_paid_up_to_the_installment(): void
    {
        foreach ([0, 0.01, 99.99, 600, 1000, 1000.01, 5000] as $paid) {
            foreach ([[600, 400], [0, 400], [300, 0], [3083.33, 916.67], [0.01, 0.01]] as [$interestDue, $principleDue]) {
                $interest = LoanScheduleSettlement::interestPaidFor($paid, $interestDue);
                $principle = LoanScheduleSettlement::principlePaidFor($paid, $interestDue, $principleDue);

                $this->assertEqualsWithDelta(min($paid, $interestDue + $principleDue), $interest + $principle, 0.001, "paid {$paid} on {$interestDue}+{$principleDue}");
                $this->assertGreaterThanOrEqual(0, $interest);
                $this->assertGreaterThanOrEqual(0, $principle);
                $this->assertLessThanOrEqual($interestDue + 1e-9, $interest);
                $this->assertLessThanOrEqual($principleDue + 1e-9, $principle);
            }
        }
    }

    /** No principle is ever paid while some of the interest is still owed. */
    public function test_no_principle_is_paid_before_the_interest_is_settled(): void
    {
        foreach ([1, 250, 599.99] as $paid) {
            $this->assertSame(0.0, (float) LoanScheduleSettlement::principlePaidFor($paid, 600, 400), "paid {$paid}");
        }
    }
}

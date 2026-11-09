<?php

namespace Database\Factories;

use App\Models\LoanSchedule;
use App\Models\MediumTermLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One installment of a medium-term loan. Nothing paid yet: remaining is the whole installment.
 * Its status (not_due_yet, past_due, paid...) is worked out by the loan_schedules trigger.
 */
class LoanScheduleFactory extends Factory
{
    protected $model = LoanSchedule::class;

    public function definition(): array
    {
        return [
            'medium_term_loan_id' => MediumTermLoanFactory::new(),
            'company_id' => fn (array $attributes) => MediumTermLoan::find($attributes['medium_term_loan_id'])?->company_id,
            'date' => '2020-01-31',
            'schedule_payment' => 100000,
            'interest_amount' => 20000,
            'principle_amount' => 80000,
            'remaining' => fn (array $attributes) => $attributes['schedule_payment'],
        ];
    }

    public function of(MediumTermLoan $loan): static
    {
        return $this->state(fn () => ['medium_term_loan_id' => $loan->id, 'company_id' => $loan->company_id]);
    }
}

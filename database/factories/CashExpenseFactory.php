<?php

namespace Database\Factories;

use App\Models\CashExpense;
use App\Models\CashExpenseCategoryName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An ordinary (single-line) cash expense. Saved through the model, so it gets its comment and
 * serial number like one entered on the screen. Only the row itself — no payment details and
 * no statement movement; a test that needs those writes them.
 */
class CashExpenseFactory extends Factory
{
    protected $model = CashExpense::class;

    public function definition(): array
    {
        return [
            'cash_expense_category_name_id' => CashExpenseCategoryNameFactory::new(),
            'company_id' => fn (array $attributes) => CashExpenseCategoryName::find($attributes['cash_expense_category_name_id'])?->company_id,
            'type' => CashExpense::CASH_PAYMENT,
            'payment_date' => '2020-01-15',
            'paid_amount' => 1000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ];
    }

    public function forExpenseName(CashExpenseCategoryName $name): static
    {
        return $this->state(fn () => ['cash_expense_category_name_id' => $name->id, 'company_id' => $name->company_id]);
    }
}

<?php

namespace Database\Factories;

use App\Models\CashExpenseCategory;
use App\Models\CashExpenseCategoryName;
use Illuminate\Database\Eloquent\Factories\Factory;

/** An expense name (sub-category) under a category, of the category's company. */
class CashExpenseCategoryNameFactory extends Factory
{
    protected $model = CashExpenseCategoryName::class;

    public function definition(): array
    {
        return [
            'cash_expense_category_id' => CashExpenseCategoryFactory::new(),
            'company_id' => fn (array $attributes) => CashExpenseCategory::find($attributes['cash_expense_category_id'])?->company_id,
            'name' => 'Test Expense '.$this->faker->unique()->numerify('####'),
        ];
    }

    public function in(CashExpenseCategory $category): static
    {
        return $this->state(fn () => ['cash_expense_category_id' => $category->id, 'company_id' => $category->company_id]);
    }
}

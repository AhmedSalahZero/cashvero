<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A bank in the global banks list (not owned by any company). */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        $name = 'Test Bank '.$this->faker->unique()->numerify('####');

        return [
            'name_en' => $name,
            'name_ar' => $name,
            'view_name' => $name,
        ];
    }
}

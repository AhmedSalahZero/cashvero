<?php

namespace Database\Factories;

use App\Models\ForeignExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForeignExchangeRateFactory extends Factory
{
    protected $model = ForeignExchangeRate::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'from_currency' => 'USD',
            'to_currency' => 'EGP',
            'date' => '2020-01-01',
            'exchange_rate' => 50,
        ];
    }
}

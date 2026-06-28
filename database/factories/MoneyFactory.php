<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Money;
use Faker\Generator as Faker;

$factory->define(Money::class, function (Faker $faker) {
    return [
        'type'=>['cash','cheque','transfer','deposit'][$faker->numberBetween(0,3)],
		'amount'=>$faker->numberBetween(10000,250000)
    ];
});

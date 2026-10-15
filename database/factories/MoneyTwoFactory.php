<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\MoneyTwo;
use Faker\Generator as Faker;

$factory->define(MoneyTwo::class, function (Faker $faker) {
    return [
		'cash'=>$faker->numberBetween(10000,250000),
		'cheque'=>$faker->numberBetween(10000,250000),
		'transfer'=>$faker->numberBetween(10000,250000),
		'deposit'=>$faker->numberBetween(10000,250000),
    ];
});

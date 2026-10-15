<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Test;
use Faker\Generator as Faker;

$factory->define(Test::class, function (Faker $faker) {
    return [
		'debit'=>$faker->numberBetween(100,1000),
		'credit'=>$faker->numberBetween(100,1000),
    ];
});

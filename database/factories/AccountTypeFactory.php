<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\AccountType;
use Faker\Generator as Faker;

$factory->define(AccountType::class, function (Faker $faker) {
    return [
        'name_en'=>$this->faker->name ,
		'name_ar'=>$this->faker->name
    ];
});

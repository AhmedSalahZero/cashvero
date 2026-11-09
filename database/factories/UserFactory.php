<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    /** Hashed once per run: the User model refuses a hash made with a different cost. */
    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => 'test-'.Str::lower(Str::random(12)).'@example.test',
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}

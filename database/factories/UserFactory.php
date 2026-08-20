<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::slug(fake()->unique()->userName()),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'is_super_user' => false,
            'must_change_password' => false,
            'created_by' => null,
        ];
    }

    public function superUser(): static
    {
        return $this->state(fn () => ['is_super_user' => true]);
    }
}

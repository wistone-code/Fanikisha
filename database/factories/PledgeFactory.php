<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Database\Eloquent\Factories\Factory;

class PledgeFactory extends Factory
{
    protected $model = Pledge::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'phone' => '255'.fake()->numerify('7########'),
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'paid' => 0,
        ];
    }
}

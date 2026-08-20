<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->company(),
            'service' => fake()->randomElement(['Catering', 'Photography', 'Decor', 'Music', 'Venue']),
            'budget' => fake()->randomFloat(2, 100000, 3000000),
            'paid' => 0,
            'phone' => '255'.fake()->numerify('7########'),
        ];
    }
}

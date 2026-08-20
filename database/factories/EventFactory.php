<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName().'\'s '.fake()->randomElement(Event::TYPES),
            'event_type' => fake()->randomElement(Event::TYPES),
            'place' => fake()->city(),
            'event_date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'pledge_deadline' => fake()->dateTimeBetween('now', '+1 week'),
            'created_by' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'start' => fake()->dateTimeBetween('-1 month', '+1 year'),
            'rounds' => fake()->numberBetween(2, 9),
            'games' => fake()->numberBetween(2, 9),
            'winpoints' => fake()->randomElement([11, 15, 21]),
            'private' => false,
            'created_by' => User::factory(),
        ];
    }
}

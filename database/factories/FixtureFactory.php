<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    protected $model = Fixture::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'team1_id' => Team::factory(),
            'team2_id' => Team::factory(),
            'round' => 1,
            'table_number' => 1,
        ];
    }
}

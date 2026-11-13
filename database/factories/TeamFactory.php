<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Team '.fake()->unique()->word(),
        ];
    }

    /**
     * A team with all four members, ready to play.
     */
    public function complete(): static
    {
        return $this->has(User::factory()->count(Team::SIZE), 'members');
    }
}

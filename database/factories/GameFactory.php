<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'status' => Game::RUNNING,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => Game::DRAFT]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => ['status' => Game::LOCKED]);
    }
}

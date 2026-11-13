<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // There is only one game, so challenges join it (or create a running one in tests).
            'game_id' => fn () => Game::query()->oldest('id')->value('id') ?? Game::factory(),
            'number' => fake()->unique()->numberBetween(1, 999),
            'category' => fake()->randomElement(['Crypto', 'Web', 'Forensics']),
            'title' => fake()->words(2, true),
            'points' => 100,
            'description' => fake()->sentence(),
            'hint' => fake()->sentence(),
            'links' => ['ctf/example.txt'],
            'flag_hash' => hash('sha256', 'CTF{'.fake()->unique()->word().'_factory}'),
        ];
    }

    /**
     * A 0-point warm-up that unlocks the given category.
     */
    public function warmUp(string $unlocksCategory): static
    {
        return $this->state(fn (): array => ['category' => 'Warm-up', 'unlocks_category' => $unlocksCategory, 'points' => 0]);
    }

    /**
     * A challenge whose answer is the given flag.
     */
    public function withFlag(string $flag): static
    {
        return $this->state(fn (): array => ['flag_hash' => hash('sha256', $flag)]);
    }
}

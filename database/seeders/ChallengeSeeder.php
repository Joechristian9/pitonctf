<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Game;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ChallengeSeeder extends Seeder
{
    /**
     * Load the generated challenges into the game. Upserting by game and number
     * makes reruns safe and keeps existing solves, because challenge ids never change.
     */
    public function run(): void
    {
        // A new game starts as a draft, so students only see it once the admin starts it.
        $game = Game::current();

        $rows = collect(File::json(database_path('data/challenges.json')))
            ->map(fn (array $challenge): array => [
                ...$challenge,
                'game_id' => $game->id,
                'links' => json_encode($challenge['links']),
            ])
            ->all();

        Challenge::upsert(
            $rows,
            uniqueBy: ['game_id', 'number'],
            update: ['category', 'unlocks_category', 'title', 'points', 'description', 'hint', 'links', 'flag_hash'],
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    /**
     * Rank teams by points; on a tie, whichever team reached that score first wins.
     */
    public function index(): Response
    {
        // Warm-ups are practice: they count for neither the solved total nor the tie-break time.
        $realSolves = fn (Builder $solves) => $solves->whereHas('challenge', fn (Builder $challenge) => $challenge->whereNull('unlocks_category'));

        $scoreboard = Team::query()
            ->select(['id', 'name'])
            // A team the admin is still filling has nobody playing for it yet.
            ->has('members')
            ->with(['members' => fn ($members) => $members->select(['id', 'name', 'team_id'])->orderBy('name')])
            ->withCount(['solves' => $realSolves])
            ->withSum(['solves' => $realSolves], 'points')
            ->withMax(['solves' => $realSolves], 'created_at')
            ->orderByDesc('solves_sum_points')
            ->orderBy('solves_max_created_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->pluck('name'),
                'solved' => $team->solves_count,
                'points' => (int) $team->solves_sum_points,
            ]);

        return Inertia::render('scoreboard', [
            'game' => Game::current()->only(['name', 'status']),
            'scoreboard' => $scoreboard,
        ]);
    }
}

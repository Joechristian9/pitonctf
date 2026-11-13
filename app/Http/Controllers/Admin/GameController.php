<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameRequest;
use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\Solve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * The admin home: the game's status, details and challenges.
     */
    public function show(): Response
    {
        $game = Game::current();

        return Inertia::render('admin/game', [
            'game' => $game->only(['name', 'description', 'status']),
            'challenges' => $game->challenges()
                ->withCount('solves')
                ->orderBy('number')
                ->get()
                ->map(fn (Challenge $challenge): array => [
                    'id' => $challenge->id,
                    'number' => $challenge->number,
                    'category' => $challenge->category,
                    'unlocks_category' => $challenge->unlocks_category,
                    'title' => $challenge->title,
                    'points' => $challenge->points,
                    'files' => count($challenge->links),
                    'solves' => $challenge->solves_count,
                ]),
        ]);
    }

    public function update(GameRequest $request): RedirectResponse
    {
        Game::current()->update($request->validated());

        return back();
    }

    /**
     * Start, lock or return the game to draft.
     */
    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(Game::STATUSES)]]);

        Game::current()->update($validated);

        return back();
    }

    /**
     * Start the competition over: every team loses its solves, tries and opened hints
     * (warm-ups included, so categories lock again). Teams, students and challenges stay.
     */
    public function resetScores(): RedirectResponse
    {
        DB::transaction(function (): void {
            Solve::query()->delete();
            Attempt::query()->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scores reset. Every team starts from zero.']);

        return back();
    }
}

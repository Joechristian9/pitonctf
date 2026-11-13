<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitFlagRequest;
use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\Solve;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    /**
     * Show the game's challenges, grouped by category, with the team's progress.
     * Students see challenges only once the game has started and they are in a team.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $game = Game::current();
        $team = $user->team;
        $teamSolves = $team?->solves()->with('solver:id,name')->get()->keyBy('challenge_id') ?? collect();
        $attempts = $team?->attempts()->get()->keyBy('challenge_id') ?? collect();
        $canSeeChallenges = $game->isVisibleTo($user) && ($user->isAdmin() || $team !== null);

        $challenges = $canSeeChallenges
            // Warm-ups first, so their category is listed at the top of the page.
            ? $game->challenges()->orderByRaw('CASE WHEN unlocks_category IS NULL THEN 1 ELSE 0 END')->orderBy('number')->get()
            : collect();

        $lockedCategories = $user->isAdmin()
            ? collect()
            : $challenges
                ->filter(fn (Challenge $challenge): bool => $challenge->isWarmUp() && ! $teamSolves->has($challenge->id))
                ->mapWithKeys(fn (Challenge $warmUp): array => [$warmUp->unlocks_category => $warmUp->title]);

        $categories = $challenges
            ->map(function (Challenge $challenge) use ($teamSolves, $attempts, $lockedCategories): array {
                $solve = $teamSolves->get($challenge->id);
                $attempt = $attempts->get($challenge->id);
                $lockedBy = $lockedCategories->get($challenge->category);
                // A free hint (warm-ups) is no secret; a paid one stays on the server until the team pays for it.
                $isHintVisible = $solve !== null || $attempt?->hint_used || $challenge->hintCost() === 0;

                // Locked challenges only show their title and points, so nobody can start early.
                return $lockedBy !== null ? [
                    'id' => $challenge->id,
                    'category' => $challenge->category,
                    'title' => $challenge->title,
                    'points' => $challenge->points,
                    'locked_by' => $lockedBy,
                ] : [
                    'id' => $challenge->id,
                    'category' => $challenge->category,
                    'unlocks_category' => $challenge->unlocks_category,
                    'title' => $challenge->title,
                    'points' => $challenge->points,
                    'description' => $challenge->description,
                    'has_hint' => filled($challenge->hint),
                    'hint' => $isHintVisible ? $challenge->hint : null,
                    'hint_cost' => $challenge->hintCost(),
                    'links' => array_map(fn (string $path): string => asset($path), $challenge->links),
                    'solved' => $solve !== null,
                    'solved_by' => $solve?->solver?->name,
                    'earned' => $solve?->points,
                    'tries_left' => $challenge->isWarmUp() ? null : max(0, Challenge::MAX_TRIES - ($attempt->tries_used ?? 0)),
                    'locked_by' => null,
                ];
            })
            ->groupBy('category');

        return Inertia::render('challenges', [
            'game' => $game->only(['name', 'description', 'status']),
            'team' => $team === null ? null : [
                'name' => $team->name,
                'members' => $team->members()->orderBy('name')->pluck('name'),
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Check a submitted flag for the student's team. Each try counts toward the team's limit;
     * a correct answer records one solve for the whole team.
     */
    public function submit(SubmitFlagRequest $request, Challenge $challenge): RedirectResponse
    {
        $user = $request->user();

        if (! $challenge->game->isRunning()) {
            return $this->result($challenge, false, 'This game is not running, so flags cannot be submitted.');
        }

        $team = $user->team;
        if ($team === null) {
            return $this->result($challenge, false, 'You need to be in a team to submit flags. Ask your instructor to add you to one.');
        }

        if ($challenge->isLockedFor($user)) {
            return $this->result($challenge, false, 'Your team must solve the warm-up for this category first.');
        }

        if ($team->solves()->where('challenge_id', $challenge->id)->exists()) {
            return $this->result($challenge, true, 'Your team already solved this one.');
        }

        if ($challenge->isWarmUp()) {
            return $this->submitWarmUp($request, $challenge, $team);
        }

        $attempt = Attempt::createOrFirst(['team_id' => $team->id, 'challenge_id' => $challenge->id]);

        // Reserve the try in one conditional UPDATE, so teammates submitting at once cannot go past the limit.
        $isTryReserved = Attempt::whereKey($attempt->id)
            ->where('tries_used', '<', Challenge::MAX_TRIES)
            ->increment('tries_used') === 1;

        if (! $isTryReserved) {
            return $this->result($challenge, false, 'Your team has no tries left. This challenge is locked.');
        }

        $attempt->refresh();

        if (! $challenge->isCorrectFlag($request->validated('flag'))) {
            $triesLeft = Challenge::MAX_TRIES - $attempt->tries_used;

            return $this->result($challenge, false, $triesLeft > 0
                ? "Not quite. Your team has {$triesLeft} ".Str::plural('try', $triesLeft).' left.'
                : 'Not quite. Your team has no tries left, so this challenge is now locked.');
        }

        $points = $attempt->hint_used ? $challenge->points - $challenge->hintCost() : $challenge->points;

        // createOrFirst relies on the unique index, so two teammates cannot score the same challenge twice.
        $solve = Solve::createOrFirst(
            ['team_id' => $team->id, 'challenge_id' => $challenge->id],
            ['user_id' => $user->id, 'points' => $points],
        );

        return $this->result(
            $challenge,
            true,
            $solve->wasRecentlyCreated ? "Correct! +{$points} points for {$team->name}." : 'Your team already solved this one.',
        );
    }

    /**
     * Reveal the hint for the whole team; it lowers the reward by the hint cost if the team solves it afterwards.
     */
    public function hint(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_if(blank($challenge->hint), 404);
        abort_unless($challenge->game->isRunning(), 403);

        $team = $request->user()->team;
        abort_if($team === null || $challenge->isLockedFor($request->user()), 403);

        Attempt::createOrFirst(['team_id' => $team->id, 'challenge_id' => $challenge->id])
            ->update(['hint_used' => true]);

        return back();
    }

    /**
     * Warm-ups have no try limit (a team must never lock itself out of a whole category) and score nothing.
     */
    private function submitWarmUp(SubmitFlagRequest $request, Challenge $challenge, Team $team): RedirectResponse
    {
        if (! $challenge->isCorrectFlag($request->validated('flag'))) {
            return $this->result($challenge, false, 'Not quite. Warm-ups have no try limit, so keep going.');
        }

        Solve::createOrFirst(
            ['team_id' => $team->id, 'challenge_id' => $challenge->id],
            ['user_id' => $request->user()->id, 'points' => 0],
        );

        return $this->result($challenge, true, "Correct! {$challenge->unlocks_category} challenges are now unlocked for {$team->name}.");
    }

    private function result(Challenge $challenge, bool $isCorrect, string $message): RedirectResponse
    {
        Inertia::flash('flagResult', [
            'challenge_id' => $challenge->id,
            'ok' => $isCorrect,
            'message' => $message,
        ]);

        return back();
    }
}

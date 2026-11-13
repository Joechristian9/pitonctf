<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\GameController;
use App\Http\Requests\SubmitFlagRequest;
use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Solve;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChallengeController extends Controller
{
    /**
     * Show the game's challenges, grouped by category, with the team's progress.
     * Students see challenges only once the game has started and they are in a team.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Admins manage the challenges at the same address students play them.
        if ($user->isAdmin()) {
            return app(GameController::class)->show();
        }

        $game = Game::current();
        $team = $user->team;
        $teamSolves = $team?->solves()->with('solver:id,name')->get()->keyBy('challenge_id') ?? collect();
        $attempts = $team?->attempts()->get()->keyBy('challenge_id') ?? collect();
        $guesses = $team?->guesses()->with('user:id,name')->latest('id')->get()->groupBy('challenge_id') ?? collect();
        $canSeeChallenges = $game->isVisibleTo($user) && $team !== null;

        $challenges = $canSeeChallenges
            // Warm-ups first, so their category is listed at the top of the page.
            ? $game->challenges()->orderByRaw('CASE WHEN unlocks_category IS NULL THEN 1 ELSE 0 END')->orderBy('number')->get()
            : collect();

        $lockedCategories = $challenges
            ->filter(fn (Challenge $challenge): bool => $challenge->isWarmUp() && ! $teamSolves->has($challenge->id))
            ->mapWithKeys(fn (Challenge $warmUp): array => [$warmUp->unlocks_category => $warmUp->title]);

        $categories = $challenges
            ->map(function (Challenge $challenge) use ($teamSolves, $attempts, $guesses, $lockedCategories): array {
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
                    // Newest first, capped: warm-ups have no try limit, so a scripted guesser could grow this list without end.
                    'guesses' => $guesses->get($challenge->id, collect())
                        ->take(Guess::SHOWN)
                        ->map(fn (Guess $guess): array => ['flag' => $guess->flag, 'by' => $guess->user?->name])
                        ->values(),
                    'writeup' => $attempt?->writeup_path === null ? null : [
                        'name' => $attempt->writeup_name,
                        'url' => route('writeups.show', $attempt),
                    ],
                    'locked_by' => null,
                ];
            })
            ->groupBy('category');

        return Inertia::render('challenges', [
            'game' => $game->forPage(),
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
            $this->recordGuess($request, $challenge, $team);
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
     * Attach the team's write-up (PDF or Word) to a challenge, any time; a new upload replaces the old one.
     */
    public function uploadWriteup(Request $request, Challenge $challenge): RedirectResponse
    {
        $message = 'Upload a PDF or Word (.docx) file of 10 MB or less.';
        $request->validate(
            ['writeup' => ['required', 'file', 'max:10240', 'extensions:pdf,docx', 'mimes:pdf,docx']],
            ['writeup.required' => 'Choose a file to upload.', 'writeup.max' => $message, 'writeup.extensions' => $message, 'writeup.mimes' => $message],
        );

        $user = $request->user();
        $team = $user->team;
        abort_if($team === null || ! $challenge->game->isVisibleTo($user) || $challenge->isLockedFor($user), 403);

        $file = $request->file('writeup');
        // The stored name is ours, never the student's, so nothing they name a file can be run or reach other paths.
        $path = $file->storeAs("writeups/{$team->id}", "{$challenge->id}.".strtolower($file->getClientOriginalExtension()), 'local');

        $attempt = Attempt::createOrFirst(['team_id' => $team->id, 'challenge_id' => $challenge->id]);
        if ($attempt->writeup_path !== null && $attempt->writeup_path !== $path) {
            Storage::disk('local')->delete($attempt->writeup_path);
        }
        $attempt->update(['writeup_path' => $path, 'writeup_name' => Str::limit($file->getClientOriginalName(), 200, '')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Write-up uploaded.']);

        return back();
    }

    /**
     * Only the team that wrote it and the admins can read a write-up.
     */
    public function downloadWriteup(Request $request, Attempt $attempt): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $attempt->team_id === $user->team_id, 403);
        abort_if($attempt->writeup_path === null, 404);

        return Storage::disk('local')->download($attempt->writeup_path, $attempt->writeup_name);
    }

    /**
     * Warm-ups have no try limit (a team must never lock itself out of a whole category) and score nothing.
     */
    private function submitWarmUp(SubmitFlagRequest $request, Challenge $challenge, Team $team): RedirectResponse
    {
        if (! $challenge->isCorrectFlag($request->validated('flag'))) {
            $this->recordGuess($request, $challenge, $team);

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

    /**
     * Keep a wrong flag, so the whole team sees what was already tried.
     */
    private function recordGuess(SubmitFlagRequest $request, Challenge $challenge, Team $team): void
    {
        Guess::create([
            'team_id' => $team->id,
            'challenge_id' => $challenge->id,
            'user_id' => $request->user()->id,
            'flag' => $request->validated('flag'),
        ]);
    }
}

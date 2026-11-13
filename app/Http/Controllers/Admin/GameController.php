<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameRequest;
use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Solve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $writeups = Attempt::query()
            ->whereNotNull('writeup_path')
            ->join('teams', 'teams.id', '=', 'attempts.team_id')
            ->orderBy('teams.name')
            ->get(['attempts.id', 'attempts.challenge_id', 'teams.name as team_name'])
            ->groupBy('challenge_id');

        return Inertia::render('admin/game', [
            'game' => $game->forPage(),
            'challenges' => $game->challenges()
                ->withCount('solves')
                ->orderBy('number')
                ->get()
                ->map(fn (Challenge $challenge): array => [
                    'writeups' => $writeups->get($challenge->id, collect())
                        ->map(fn (Attempt $attempt): array => ['team' => $attempt->getAttribute('team_name'), 'url' => route('writeups.show', $attempt)])
                        ->values(),
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
     * Share a write-up template (PDF or Word) with every team; a new upload replaces the old one.
     */
    public function uploadWriteupTemplate(Request $request): RedirectResponse
    {
        $message = 'Upload a PDF or Word (.docx) file of 10 MB or less.';
        $request->validate(
            ['template' => ['required', 'file', 'max:10240', 'extensions:pdf,docx', 'mimes:pdf,docx']],
            ['template.required' => 'Choose a file to upload.', 'template.max' => $message, 'template.extensions' => $message, 'template.mimes' => $message],
        );

        $game = Game::current();
        $file = $request->file('template');
        // Our own name, so nothing the admin's file is called can reach other paths on the public disk.
        $path = $file->storeAs('templates', "writeup-template.{$file->extension()}", 'ctf');

        if ($game->writeup_template !== null && $game->writeup_template !== $path) {
            Storage::disk('ctf')->delete($game->writeup_template);
        }
        // touch() so the template's ?v= changes even when the path stays the same.
        $game->fill(['writeup_template' => $path])->touch();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Write-up template uploaded.']);

        return back();
    }

    /**
     * Stop sharing the template; the teams' own write-ups are not touched.
     */
    public function removeWriteupTemplate(): RedirectResponse
    {
        $game = Game::current();

        if ($game->writeup_template !== null) {
            $path = $game->writeup_template;
            $game->update(['writeup_template' => null]);
            // The file goes only after the row, so a failed save never leaves a link to a missing file.
            Storage::disk('ctf')->delete($path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Write-up template removed.']);

        return back();
    }

    /**
     * Start the competition over: every team loses its solves, tries, tried flags, write-ups and opened hints
     * (warm-ups included, so categories lock again). Teams, students and challenges stay.
     */
    public function resetScores(): RedirectResponse
    {
        DB::transaction(function (): void {
            Solve::query()->delete();
            Attempt::query()->delete();
            Guess::query()->delete();
        });
        // Write-ups belonged to the deleted attempts.
        Storage::disk('local')->deleteDirectory('writeups');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scores reset. Every team starts from zero.']);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/teams', [
            'teamSize' => Team::SIZE,
            'teams' => Team::query()
                ->with(['members' => fn ($members) => $members->select(['id', 'name', 'email', 'team_id'])->orderBy('name')])
                ->withSum('solves', 'points')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'codename' => $team->codename,
                    'points' => (int) $team->solves_sum_points,
                    'members' => $team->members->map->only(['id', 'name', 'email'])->values(),
                ]),
            // Students without a team, ready to be placed. Admins never play.
            'unassigned' => User::query()
                ->withoutRole(User::ADMIN)
                ->whereNull('team_id')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(Team::class), Rule::unique(Team::class, 'codename')],
        ]);

        $team = Team::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Created {$team->name}."]);

        return back();
    }

    /**
     * Set the name students see on the scoreboard; empty shows the real team name there.
     */
    public function updateCodename(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            // Not another team's real name either, or two rows would show the same name.
            'codename' => ['nullable', 'string', 'max:100', Rule::unique(Team::class)->ignore($team), Rule::unique(Team::class, 'name')->ignore($team)],
        ], ['codename.unique' => 'Another team already uses this name or codename.']);

        $team->update(['codename' => $validated['codename'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Saved the codename for {$team->name}."]);

        return back();
    }

    /**
     * Deleting a team also deletes its solves and tries; its members become teamless.
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();
        Storage::disk('local')->deleteDirectory("writeups/{$team->id}");

        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted {$team->name}."]);

        return back();
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $request->validate(['user_id' => ['required', 'integer', Rule::exists(User::class, 'id')]]);
        $student = User::findOrFail($request->integer('user_id'));

        if ($student->isAdmin()) {
            throw ValidationException::withMessages(['user_id' => 'Admins cannot join a team.']);
        }
        if ($student->team_id !== null) {
            throw ValidationException::withMessages(['user_id' => "{$student->name} is already in a team."]);
        }
        if ($team->members()->count() >= Team::SIZE) {
            throw ValidationException::withMessages(['user_id' => "{$team->name} already has ".Team::SIZE.' members.']);
        }

        $student->forceFill(['team_id' => $team->id])->save();

        return back();
    }

    /**
     * The team keeps everything it solved; the student just leaves it.
     */
    public function removeMember(Team $team, User $user): RedirectResponse
    {
        abort_unless($user->team_id === $team->id, 404);

        $user->forceFill(['team_id' => null])->save();

        return back();
    }
}

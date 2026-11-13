<?php

namespace App\Http\Controllers;

use App\Models\Solve;
use App\Models\Team;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    private const CHART_TEAMS = 10;

    /**
     * Rank teams by points; on a tie, whichever team reached that score first wins.
     * The top teams also get their score over time for the progression chart.
     */
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->select(['id', 'name', 'codename'])
            // A team the admin is still filling has nobody playing for it yet.
            ->has('members')
            ->withSum(['solves' => $this->realSolves(...)], 'points')
            ->withMax(['solves' => $this->realSolves(...)], 'created_at')
            ->orderByDesc('solves_sum_points')
            ->orderBy('solves_max_created_at')
            // Same order as the names on screen, so a tie reveals nothing about real names.
            ->orderByRaw('COALESCE(codename, name)')
            ->get();

        // Students get only the codename: no real name, members or team id. A soft cover only: a team can still
        // find its row by its points or by which row moves after it solves. Admins also see the real name.
        $isAdmin = $request->user()->isAdmin();
        $label = fn (Team $team): array => [
            'name' => $team->codename ?? $team->name,
            ...($isAdmin ? ['real_name' => $team->name] : []),
        ];

        return Inertia::render('scoreboard', [
            'scoreboard' => $teams->map(fn (Team $team): array => [...$label($team), 'points' => (int) $team->solves_sum_points]),
            'progression' => $this->progression($teams->take(self::CHART_TEAMS), $label),
        ]);
    }

    /**
     * Warm-ups are practice: they count for neither points nor the tie-break time.
     *
     * @param  Builder<Solve>  $solves
     */
    private function realSolves(Builder $solves): void
    {
        $solves->whereHas('challenge', fn (Builder $challenge) => $challenge->whereNull('unlocks_category'));
    }

    /**
     * Each team's running total, as [time in ms, points] steps from the first solve among these teams until now.
     *
     * @param  Collection<int, Team>  $teams
     * @param  Closure(Team): array<string, string>  $label
     * @return list<array<string, mixed>>
     */
    private function progression(Collection $teams, Closure $label): array
    {
        $allSolves = Solve::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->where(fn (Builder $solves) => $this->realSolves($solves))
            ->orderBy('created_at')
            ->get(['team_id', 'points', 'created_at']);

        // Sorted by time, so the first solve marks the start of every line.
        $start = $allSolves->first()?->created_at;
        if ($start === null) {
            return [];
        }
        $solvesByTeam = $allSolves->groupBy('team_id');
        $now = now()->getTimestampMs();

        return array_values($teams->map(function (Team $team) use ($solvesByTeam, $start, $now, $label): array {
            $total = 0;
            $steps = [['t' => $start->getTimestampMs(), 'points' => 0]];
            foreach ($solvesByTeam->get($team->id, collect()) as $solve) {
                $total += $solve->points;
                $steps[] = ['t' => $solve->created_at?->getTimestampMs() ?? $now, 'points' => $total];
            }
            $steps[] = ['t' => $now, 'points' => $total];

            return [...$label($team), 'steps' => $steps];
        })->all());
    }
}

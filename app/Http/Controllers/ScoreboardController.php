<?php

namespace App\Http\Controllers;

use App\Models\Solve;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
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
    public function index(): Response
    {
        $scoreboard = Team::query()
            ->select(['id', 'name'])
            // A team the admin is still filling has nobody playing for it yet.
            ->has('members')
            ->with(['members' => fn ($members) => $members->select(['id', 'name', 'team_id'])->orderBy('name')])
            ->withSum(['solves' => $this->realSolves(...)], 'points')
            ->withMax(['solves' => $this->realSolves(...)], 'created_at')
            ->orderByDesc('solves_sum_points')
            ->orderBy('solves_max_created_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->pluck('name'),
                'points' => (int) $team->solves_sum_points,
            ]);

        return Inertia::render('scoreboard', [
            'scoreboard' => $scoreboard,
            'progression' => $this->progression($scoreboard->take(self::CHART_TEAMS)),
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
     * @param  Collection<int, array{id: int, name: string, members: Collection<int|string, mixed>, points: int}>  $teams
     * @return list<array{id: int, name: string, steps: list<array{t: int, points: int}>}>
     */
    private function progression(Collection $teams): array
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

        return array_values($teams->map(function (array $team) use ($solvesByTeam, $start, $now): array {
            $total = 0;
            $steps = [['t' => $start->getTimestampMs(), 'points' => 0]];
            foreach ($solvesByTeam->get($team['id'], collect()) as $solve) {
                $total += $solve->points;
                $steps[] = ['t' => $solve->created_at?->getTimestampMs() ?? $now, 'points' => $total];
            }
            $steps[] = ['t' => $now, 'points' => $total];

            return ['id' => $team['id'], 'name' => $team['name'], 'steps' => $steps];
        })->all());
    }
}

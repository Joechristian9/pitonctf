<?php

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

test('teams are ranked by earned points with ties going to the earliest last solve', function () {
    $hard = Challenge::factory()->create(['points' => 100]);
    [$easy, $otherEasy] = Challenge::factory()->count(2)->create(['points' => 50]);

    $alpha = Team::factory()->complete()->create(['name' => 'Alpha']);
    $bravo = Team::factory()->complete()->create(['name' => 'Bravo']);
    $charlie = Team::factory()->complete()->create(['name' => 'Charlie']);
    $delta = Team::factory()->complete()->create(['name' => 'Delta']);
    Team::factory()->create(['name' => 'Empty Team']);
    $solve = fn (Team $team, Challenge $challenge, int $points) => Solve::create([
        'team_id' => $team->id,
        'challenge_id' => $challenge->id,
        'user_id' => $team->members()->value('id'),
        'points' => $points,
    ]);

    $this->travelTo('2026-11-04 09:00');
    $solve($bravo, $easy, 50);
    $this->travelTo('2026-11-04 09:30');
    $solve($bravo, $otherEasy, 50);
    $this->travelTo('2026-11-04 09:45');
    // Delta opened the hint first, so it earned half.
    $solve($delta, $easy, 25);
    $this->travelTo('2026-11-04 10:00');
    $solve($alpha, $hard, 100);

    $response = $this->actingAs($charlie->members()->firstOrFail())->get(route('scoreboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('scoreboard')
        ->where('scoreboard.0.name', 'Bravo')
        ->where('scoreboard.0.solved', 2)
        ->where('scoreboard.0.points', 100)
        ->has('scoreboard.0.members', 4)
        ->where('scoreboard.2.name', 'Delta')
        ->where('scoreboard.2.points', 25));

    expect(collect($response->inertiaProps('scoreboard'))->pluck('name')->all())
        ->toBe(['Bravo', 'Alpha', 'Delta', 'Charlie']);
});

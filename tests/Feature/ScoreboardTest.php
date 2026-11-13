<?php

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
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
        ->where('scoreboard.0.points', 100)
        ->missing('scoreboard.0.members')
        ->where('scoreboard.2.name', 'Delta')
        ->where('scoreboard.2.points', 25));

    expect(collect($response->inertiaProps('scoreboard'))->pluck('name')->all())
        ->toBe(['Bravo', 'Alpha', 'Delta', 'Charlie']);

    // Every line starts at 0 at the first solve of the game, climbs per solve, and runs on to now.
    $bravoLine = collect($response->inertiaProps('progression'))->firstWhere('name', 'Bravo');
    $firstSolveAt = Carbon::parse('2026-11-04 09:00')->getTimestampMs();
    expect($bravoLine['steps'])->toBe([
        ['t' => $firstSolveAt, 'points' => 0],
        ['t' => $firstSolveAt, 'points' => 50],
        ['t' => Carbon::parse('2026-11-04 09:30')->getTimestampMs(), 'points' => 100],
        ['t' => now()->getTimestampMs(), 'points' => 100],
    ])->and(collect($response->inertiaProps('progression'))->firstWhere('name', 'Charlie')['steps'][0]['points'])->toBe(0);
});

test('the chart shows at most the top 10 teams and nothing before the first solve', function () {
    Team::factory()->complete()->count(11)->create();

    $this->actingAs(teamPlayer())
        ->get(route('scoreboard'))
        ->assertInertia(fn (Assert $page) => $page->has('scoreboard', 12)->where('progression', []));

    Solve::create(['team_id' => Team::first()->id, 'challenge_id' => Challenge::factory()->create()->id, 'points' => 50]);

    $this->get(route('scoreboard'))
        ->assertInertia(fn (Assert $page) => $page->has('progression', 10));
});

test('students see only codenames, not real names, members or team ids', function () {
    $team = Team::factory()->complete()->create(['name' => 'Bravo', 'codename' => 'Red Falcon']);
    Solve::create(['team_id' => $team->id, 'challenge_id' => Challenge::factory()->create()->id, 'points' => 50]);

    $this->actingAs($team->members()->firstOrFail())
        ->get(route('scoreboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scoreboard.0', ['name' => 'Red Falcon', 'points' => 50])
            ->where('progression.0.name', 'Red Falcon')
            ->missing('progression.0.id')
            ->missing('progression.0.real_name'))
        ->assertDontSee('Bravo');
});

test('admins see the real name behind each codename', function () {
    Team::factory()->complete()->create(['name' => 'Bravo', 'codename' => 'Red Falcon']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('scoreboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scoreboard.0.name', 'Red Falcon')
            ->where('scoreboard.0.real_name', 'Bravo'));
});

test('the admin sets and clears a team codename, and codenames are unique', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();
    Team::factory()->create(['name' => 'Bravo', 'codename' => 'Red Falcon']);

    $this->actingAs($admin)->patch(route('admin.teams.codename', $team), ['codename' => 'Blue Owl'])->assertRedirect();
    expect($team->fresh()->codename)->toBe('Blue Owl');

    foreach (['Red Falcon', 'Bravo'] as $taken) {
        $this->patch(route('admin.teams.codename', $team), ['codename' => $taken])
            ->assertSessionHasErrors(['codename' => 'Another team already uses this name or codename.']);
    }

    $this->post(route('admin.teams.store'), ['name' => 'Red Falcon'])->assertSessionHasErrors('name');

    $this->patch(route('admin.teams.codename', $team), ['codename' => '']);
    expect($team->fresh()->codename)->toBeNull();
});

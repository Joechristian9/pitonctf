<?php

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('students cannot manage teams', function (string $method, string $uri) {
    $team = Team::factory()->create();
    $student = User::factory()->create();

    $this->actingAs($student)
        ->call($method, str_replace(['{team}', '{student}'], [(string) $team->id, (string) $student->id], $uri), ['name' => 'Sneaky', 'user_id' => $student->id])
        ->assertForbidden();
})->with([
    'list teams' => ['get', '/admin/teams'],
    'create team' => ['post', '/admin/teams'],
    'delete team' => ['delete', '/admin/teams/{team}'],
    'add member' => ['post', '/admin/teams/{team}/members'],
    'remove member' => ['delete', '/admin/teams/{team}/members/{student}'],
]);

test('the admin sees teams and the students who still need one', function () {
    $team = Team::factory()->complete()->create(['name' => 'Byte Busters']);
    $loner = User::factory()->create(['name' => 'Loner']);

    $this->get(route('admin.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/teams')
            ->where('teamSize', 4)
            ->where('teams.0.name', 'Byte Busters')
            ->has('teams.0.members', 4)
            // The admin has no team either, but admins never play.
            ->where('unassigned', [['id' => $loner->id, 'name' => 'Loner', 'email' => $loner->email]]));
});

test('a team needs a unique name', function () {
    Team::factory()->create(['name' => 'Byte Busters']);

    $this->post(route('admin.teams.store'), ['name' => 'Byte Busters'])->assertSessionHasErrors('name');
    $this->post(route('admin.teams.store'), ['name' => 'Root Rangers'])->assertRedirect();

    $this->assertDatabaseHas('teams', ['name' => 'Root Rangers']);
    $this->assertDatabaseCount('teams', 2);
});

test('the admin adds students to a team, up to four', function () {
    $team = Team::factory()->has(User::factory()->count(3), 'members')->create();
    [$fourth, $fifth] = User::factory()->count(2)->create();

    $this->post(route('admin.teams.members.store', $team), ['user_id' => $fourth->id])->assertRedirect();
    expect($fourth->fresh()->team_id)->toBe($team->id)
        ->and($team->members()->count())->toBe(4);

    $this->post(route('admin.teams.members.store', $team), ['user_id' => $fifth->id])
        ->assertSessionHasErrors(['user_id' => "{$team->name} already has 4 members."]);
    expect($fifth->fresh()->team_id)->toBeNull();
});

test('a student in another team or an admin cannot be added', function () {
    $team = Team::factory()->create();
    $taken = Team::factory()->complete()->create()->members()->firstOrFail();

    $this->post(route('admin.teams.members.store', $team), ['user_id' => $taken->id])
        ->assertSessionHasErrors(['user_id' => "{$taken->name} is already in a team."]);
    $this->post(route('admin.teams.members.store', $team), ['user_id' => $this->admin->id])
        ->assertSessionHasErrors(['user_id' => 'Admins cannot join a team.']);

    expect($team->members()->count())->toBe(0);
});

test('removing a member keeps the team solves', function () {
    $student = teamPlayer();
    $team = $student->team;
    Solve::create(['team_id' => $team->id, 'challenge_id' => Challenge::factory()->create()->id, 'user_id' => $student->id, 'points' => 100]);

    $this->delete(route('admin.teams.members.destroy', ['team' => $team, 'user' => $student]))->assertRedirect();

    expect($student->fresh()->team_id)->toBeNull()
        ->and($team->members()->count())->toBe(3)
        ->and($team->solves()->sum('points'))->toBe(100);
});

test('a member of another team cannot be removed through the wrong team', function () {
    $student = teamPlayer();

    $this->delete(route('admin.teams.members.destroy', ['team' => Team::factory()->create(), 'user' => $student]))
        ->assertNotFound();

    expect($student->fresh()->team_id)->not->toBeNull();
});

test('deleting a team deletes its solves and frees its members', function () {
    $student = teamPlayer();
    $team = $student->team;
    Solve::create(['team_id' => $team->id, 'challenge_id' => Challenge::factory()->create()->id, 'user_id' => $student->id, 'points' => 100]);

    $this->delete(route('admin.teams.destroy', $team))->assertRedirect();

    $this->assertModelMissing($team);
    $this->assertDatabaseCount('solves', 0);
    expect($student->fresh()->team_id)->toBeNull();
});

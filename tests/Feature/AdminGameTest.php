<?php

use App\Models\Attempt;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\Solve;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('students cannot use the admin area', function (string $method, string $uri) {
    Challenge::factory()->create();
    $student = User::factory()->create();

    $this->actingAs($student)
        ->call($method, str_replace('{student}', (string) $student->id, $uri), [
            'name' => 'Sneaky',
            'status' => Game::RUNNING,
        ])
        ->assertForbidden();
})->with([
    'admin home' => ['get', '/admin'],
    'edit game' => ['put', '/admin/game'],
    'start game' => ['patch', '/admin/game/status'],
    'new challenge' => ['post', '/admin/challenges'],
    'delete challenge' => ['delete', '/admin/challenges/1'],
    'reset scores' => ['delete', '/admin/game/scores'],
    'list students' => ['get', '/admin/users'],
    'add student' => ['post', '/admin/users'],
    'add class list' => ['post', '/admin/users/bulk'],
    'reset password' => ['post', '/admin/users/{student}/password'],
    'delete student' => ['delete', '/admin/users/{student}'],
]);

test('the admin home shows the game and its challenges', function () {
    $game = Game::factory()->draft()->create(['name' => 'Week 3 CTF']);
    Challenge::factory()->for($game)->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.game'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/game')
            ->where('game.name', 'Week 3 CTF')
            ->has('challenges', 2));
});

test('there is no way to create a second game', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/games', ['name' => 'Another game'])
        ->assertNotFound();

    $this->assertDatabaseCount('games', 0);
});

test('the admin can rename the game', function () {
    $game = Game::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.game.update'), ['name' => 'Final CTF', 'description' => 'Good luck.'])
        ->assertRedirect();

    expect($game->fresh())
        ->name->toBe('Final CTF')
        ->description->toBe('Good luck.');
});

test('the admin can start and lock the game', function () {
    $game = Game::factory()->draft()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('admin.game.status'), ['status' => Game::RUNNING])->assertRedirect();
    expect($game->fresh()->status)->toBe(Game::RUNNING);

    $this->patch(route('admin.game.status'), ['status' => Game::LOCKED])->assertRedirect();
    expect($game->fresh()->status)->toBe(Game::LOCKED);
});

test('an unknown game status is rejected', function () {
    $game = Game::factory()->draft()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.game.status'), ['status' => 'finished'])
        ->assertSessionHasErrors('status');

    expect($game->fresh()->status)->toBe(Game::DRAFT);
});

test('resetting scores wipes all progress but keeps teams and challenges', function () {
    $player = teamPlayer();
    $challenge = Challenge::factory()->create();
    Solve::create(['team_id' => $player->team_id, 'challenge_id' => $challenge->id, 'user_id' => $player->id, 'points' => 100]);
    Attempt::create(['team_id' => $player->team_id, 'challenge_id' => $challenge->id, 'hint_used' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.game.scores.destroy'))
        ->assertRedirect();

    $this->assertDatabaseCount('solves', 0);
    $this->assertDatabaseCount('attempts', 0);
    $this->assertModelExists($challenge);
    $this->assertModelExists($player->team);
    expect($player->fresh()->team_id)->not->toBeNull();
});

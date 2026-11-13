<?php

use App\Models\Challenge;
use App\Models\Game;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('students see no challenges before the game starts', function () {
    $challenge = Challenge::factory()->for(Game::factory()->draft())->create(['title' => 'Secret Draft']);

    $this->actingAs(teamPlayer())
        ->get(route('challenges'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.status', Game::DRAFT)
            ->where('categories', []))
        ->assertDontSee($challenge->title);
});

test('admins can preview a draft game', function () {
    Challenge::factory()->for(Game::factory()->draft())->create(['category' => 'Web']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->has('categories.Web', 1));
});

test('flags cannot be submitted unless the game is running', function (string $status) {
    $challenge = Challenge::factory()
        ->for(Game::factory()->state(['status' => $status]))
        ->withFlag('CTF{known_test_flag}')
        ->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.ok', false)
        ->assertInertiaFlash('flagResult.message', 'This game is not running, so flags cannot be submitted.');

    $this->assertDatabaseCount('solves', 0);
    $this->assertDatabaseCount('attempts', 0);
})->with([Game::DRAFT, Game::LOCKED]);

test('hints cannot be opened in a locked game', function () {
    $challenge = Challenge::factory()->for(Game::factory()->locked())->create();

    $this->actingAs(teamPlayer())
        ->post(route('challenges.hint', $challenge))
        ->assertForbidden();
});

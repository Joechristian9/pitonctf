<?php

use App\Models\Challenge;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect(route('login'));
})->with([
    'challenges page' => ['get', '/challenges'],
    'scoreboard page' => ['get', '/scoreboard'],
    'flag submission' => ['post', '/challenges/1/submit'],
    'hint reveal' => ['post', '/challenges/1/hint'],
]);

test('the challenges page lists challenges without their flag hashes or unopened hints', function () {
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create([
        'category' => 'Web',
        'links' => ['ctf/web/index.html'],
        'hint' => 'Look behind the curtain.',
    ]);

    $this->actingAs(teamPlayer())
        ->get(route('challenges'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('challenges')
            ->where('categories.Web.0.title', $challenge->title)
            ->where('categories.Web.0.links.0', asset('ctf/web/index.html'))
            ->where('categories.Web.0.solved', false)
            ->where('categories.Web.0.tries_left', 8)
            ->where('categories.Web.0.has_hint', true)
            ->where('categories.Web.0.hint', null)
            ->missing('categories.Web.0.flag_hash'))
        ->assertDontSee($challenge->flag_hash)
        ->assertDontSee('Look behind the curtain.');
});

test('a correct flag scores once for the whole team', function () {
    $player = teamPlayer();
    $teammate = $player->team->members()->whereKeyNot($player->id)->firstOrFail();
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create(['points' => 150]);

    $this->actingAs($player)
        ->from(route('challenges'))
        ->post(route('challenges.submit', $challenge), ['flag' => '  CTF{known_test_flag} '])
        ->assertRedirect(route('challenges'))
        ->assertInertiaFlash('flagResult.ok', true)
        ->assertInertiaFlash('flagResult.message', "Correct! +150 points for {$player->team->name}.");

    $this->assertDatabaseHas('solves', ['team_id' => $player->team_id, 'challenge_id' => $challenge->id, 'user_id' => $player->id, 'points' => 150]);

    $this->actingAs($teammate)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where("categories.{$challenge->category}.0.solved", true)
            ->where("categories.{$challenge->category}.0.solved_by", $player->name)
            ->where("categories.{$challenge->category}.0.earned", 150));

    $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.message', 'Your team already solved this one.');
    $this->assertDatabaseCount('solves', 1);
});

test('a wrong flag scores nothing and uses one of the team tries', function () {
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create();

    $this->actingAs(teamPlayer())
        ->post(route('challenges.submit', $challenge), ['flag' => 'CTF{wrong_guess}'])
        ->assertInertiaFlash('flagResult.ok', false)
        ->assertInertiaFlash('flagResult.message', 'Not quite. Your team has 7 tries left.');

    $this->assertDatabaseCount('solves', 0);
});

test('an empty flag is rejected', function () {
    $challenge = Challenge::factory()->create();

    $this->actingAs(teamPlayer())
        ->post(route('challenges.submit', $challenge), ['flag' => ''])
        ->assertSessionHasErrors(['flag' => 'Type a flag before submitting.']);
});

test('students cannot play without a team', function () {
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create(['hint' => 'Look up.']);

    $this->actingAs(User::factory()->create())
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where('team', null)->where('categories', []));

    $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.ok', false)
        ->assertInertiaFlash('flagResult.message', 'You need to be in a team to submit flags. Ask your instructor to add you to one.');
    $this->post(route('challenges.hint', $challenge))->assertForbidden();

    $this->assertDatabaseCount('solves', 0);
    $this->assertDatabaseCount('attempts', 0);
});

test('a team that is not full can still play', function () {
    $soloPlayer = Team::factory()->has(User::factory(), 'members')->create()->members()->firstOrFail();
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create(['category' => 'Web']);

    $this->actingAs($soloPlayer)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->has('categories.Web', 1));

    $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.ok', true);
    $this->assertDatabaseHas('solves', ['team_id' => $soloPlayer->team_id, 'challenge_id' => $challenge->id]);
});

test('the 8 tries are shared by the team, even for the correct flag', function () {
    $player = teamPlayer();
    $teammate = $player->team->members()->whereKeyNot($player->id)->firstOrFail();
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create();

    foreach (range(1, 4) as $attempt) {
        $this->actingAs($player)->post(route('challenges.submit', $challenge), ['flag' => 'CTF{wrong_guess}']);
        $this->actingAs($teammate)->post(route('challenges.submit', $challenge), ['flag' => 'CTF{wrong_guess}']);
    }

    $this->actingAs($player)
        ->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.message', 'Your team has no tries left. This challenge is locked.');

    $this->assertDatabaseCount('solves', 0);
    $this->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where("categories.{$challenge->category}.0.tries_left", 0));
});

test('a hint opened by one member is shown to the team and halves the reward', function () {
    $player = teamPlayer();
    $teammate = $player->team->members()->whereKeyNot($player->id)->firstOrFail();
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create([
        'points' => 150,
        'hint' => 'Look behind the curtain.',
    ]);

    $this->actingAs($player)->post(route('challenges.hint', $challenge))->assertRedirect();

    $this->actingAs($teammate)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where("categories.{$challenge->category}.0.hint", 'Look behind the curtain.'));

    $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.message', "Correct! +75 points for {$player->team->name}.");
    $this->assertDatabaseHas('solves', ['challenge_id' => $challenge->id, 'points' => 75]);
});

test('a challenge without a hint has nothing to reveal', function () {
    $challenge = Challenge::factory()->create(['hint' => null]);

    $this->actingAs(teamPlayer())
        ->post(route('challenges.hint', $challenge))
        ->assertNotFound();

    $this->assertDatabaseCount('attempts', 0);
});

test('flag submissions are limited to 10 per minute per student', function () {
    $challenge = Challenge::factory()->withFlag('CTF{known_test_flag}')->create();
    $this->actingAs(teamPlayer());

    foreach (range(1, 10) as $attempt) {
        $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{wrong_guess}']);
    }

    $this->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertRedirect()
        ->assertInertiaFlash('flagResult.challenge_id', $challenge->id)
        ->assertInertiaFlash('flagResult.message', 'Too many tries. Take a breath and try again in a minute.');
    $this->assertDatabaseCount('solves', 0);

    $this->actingAs(teamPlayer())
        ->post(route('challenges.submit', $challenge), ['flag' => 'CTF{known_test_flag}'])
        ->assertInertiaFlash('flagResult.ok', true);
});

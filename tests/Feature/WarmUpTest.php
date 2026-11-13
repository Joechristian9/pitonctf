<?php

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->warmUp = Challenge::factory()->warmUp('Web')->withFlag('CTF{warm_up_flag}')->create(['title' => 'Open the File', 'number' => 31]);
    $this->webChallenge = Challenge::factory()->withFlag('CTF{real_web_flag}')->create([
        'category' => 'Web',
        'title' => 'Secret Portal',
        'description' => 'Top secret description.',
    ]);
    $this->cryptoChallenge = Challenge::factory()->create(['category' => 'Crypto']);
    $this->student = teamPlayer();
});

test('a category stays locked and hides its content until its warm-up is solved', function () {
    $this->actingAs($this->student)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.Warm-up.0.title', 'Open the File')
            ->where('categories.Web.0.locked_by', 'Open the File')
            ->missing('categories.Web.0.description')
            ->missing('categories.Web.0.links')
            // A category without a warm-up is open from the start.
            ->where('categories.Crypto.0.locked_by', null))
        ->assertDontSee('Top secret description.');
});

test('flags for a locked category are refused', function () {
    $this->actingAs($this->student)
        ->post(route('challenges.submit', $this->webChallenge), ['flag' => 'CTF{real_web_flag}'])
        ->assertInertiaFlash('flagResult.ok', false)
        ->assertInertiaFlash('flagResult.message', 'Your team must solve the warm-up for this category first.');

    $this->post(route('challenges.hint', $this->webChallenge))->assertForbidden();

    $this->assertDatabaseCount('solves', 0);
    $this->assertDatabaseCount('attempts', 0);
});

test('solving the warm-up unlocks its category for that team only', function () {
    $this->actingAs($this->student)
        ->post(route('challenges.submit', $this->warmUp), ['flag' => 'CTF{warm_up_flag}'])
        ->assertInertiaFlash('flagResult.ok', true)
        ->assertInertiaFlash('flagResult.message', "Correct! Web challenges are now unlocked for {$this->student->team->name}.");

    $this->assertDatabaseHas('solves', ['team_id' => $this->student->team_id, 'challenge_id' => $this->warmUp->id, 'user_id' => $this->student->id, 'points' => 0]);

    $this->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.Web.0.locked_by', null)
            ->where('categories.Web.0.description', 'Top secret description.'));

    $this->post(route('challenges.submit', $this->webChallenge), ['flag' => 'CTF{real_web_flag}'])
        ->assertInertiaFlash('flagResult.ok', true);

    $this->actingAs(teamPlayer())
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where('categories.Web.0.locked_by', 'Open the File'));
});

test('warm-ups have no try limit', function () {
    $this->actingAs($this->student);

    foreach (range(1, Challenge::MAX_TRIES) as $attempt) {
        $this->post(route('challenges.submit', $this->warmUp), ['flag' => 'CTF{wrong}'])
            ->assertInertiaFlash('flagResult.message', 'Not quite. Warm-ups have no try limit, so keep going.');
    }

    $this->post(route('challenges.submit', $this->warmUp), ['flag' => 'CTF{warm_up_flag}'])
        ->assertInertiaFlash('flagResult.ok', true);
});

test('a warm-up hint is free and shown straight away', function () {
    $this->warmUp->update(['hint' => 'Read it backwards.']);

    $this->actingAs($this->student)
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.Warm-up.0.hint', 'Read it backwards.')
            ->where('categories.Warm-up.0.hint_cost', 0)
            ->where('categories.Warm-up.0.tries_left', null));
});

test('admins skip the warm-ups', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('challenges'))
        ->assertInertia(fn (Assert $page) => $page->where('categories.Web.0.locked_by', null));
});

test('warm-ups do not count on the scoreboard', function () {
    Solve::create(['team_id' => $this->student->team_id, 'challenge_id' => $this->warmUp->id, 'user_id' => $this->student->id, 'points' => 0]);

    $this->actingAs($this->student)
        ->get(route('scoreboard'))
        ->assertInertia(fn (Assert $page) => $page->where('scoreboard.0.solved', 0));
});

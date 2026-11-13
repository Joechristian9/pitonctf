<?php

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ms Rivera']);
    $this->actingAs($this->admin);
});

test('the admin sees every account with its team', function () {
    $team = Team::factory()->create(['name' => 'Byte Busters']);
    $student = User::factory()->create(['name' => 'Ana', 'team_id' => $team->id]);

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->where('users.0', ['id' => $student->id, 'name' => 'Ana', 'email' => $student->email, 'is_admin' => false, 'team' => 'Byte Busters'])
            ->where('users.1.is_admin', true)
            ->where('users.1.team', null));
});

test('the admin adds a verified student with the chosen password', function () {
    $this->post(route('admin.users.store'), [
        'name' => 'Maria Reyes',
        'email' => 'maria@school.example',
        'password' => 'tulip-ledger-42',
    ])->assertRedirect();

    $student = User::where('email', 'maria@school.example')->sole();
    expect($student->hasRole(User::STUDENT))->toBeTrue()
        ->and($student->isAdmin())->toBeFalse()
        ->and($student->email_verified_at)->not->toBeNull()
        ->and(Hash::check('tulip-ledger-42', $student->password))->toBeTrue();
});

test('a student needs a unique email and a valid password', function () {
    User::factory()->create(['email' => 'taken@school.example']);

    $this->post(route('admin.users.store'), [
        'name' => 'Copycat',
        'email' => 'taken@school.example',
        'password' => 'short',
    ])->assertSessionHasErrors(['email', 'password']);

    $this->assertDatabaseCount('users', 2);
});

test('a pasted class list adds every student and shows their new passwords once', function () {
    $response = $this->post(route('admin.users.bulk'), [
        'students' => "Maria Reyes, maria@school.example\n\n  Reyes, Jun\tjun@school.example  \n",
    ]);

    $response->assertRedirect();
    $credentials = session(SessionKey::FLASH_DATA)['credentials'];

    expect($credentials)->toHaveCount(2)
        ->and($credentials[0])->toMatchArray(['name' => 'Maria Reyes', 'email' => 'maria@school.example'])
        ->and($credentials[1])->toMatchArray(['name' => 'Reyes, Jun', 'email' => 'jun@school.example']);

    foreach ($credentials as $credential) {
        $student = User::where('email', $credential['email'])->sole();
        expect(Hash::check($credential['password'], $student->password))->toBeTrue()
            ->and($student->hasRole(User::STUDENT))->toBeTrue()
            ->and($student->email_verified_at)->not->toBeNull();
    }
});

test('a class list with a bad line adds nobody and names the line', function () {
    User::factory()->create(['email' => 'taken@school.example']);

    $this->post(route('admin.users.bulk'), [
        'students' => "Maria Reyes, maria@school.example\nNo Email Here\nCopycat, taken@school.example",
    ])->assertSessionHasErrors([
        'students' => 'Line 2: The email field is required.',
    ]);

    $this->assertDatabaseMissing('users', ['email' => 'maria@school.example']);
});

test('resetting a password replaces it and shows the new one once', function () {
    $student = User::factory()->create();

    $response = $this->post(route('admin.users.password', $student))->assertRedirect();

    $newPassword = session(SessionKey::FLASH_DATA)['credentials'][0]['password'];
    expect(Hash::check($newPassword, $student->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $student->fresh()->password))->toBeFalse();
});

test('the admin can delete a student but not themselves, and the team keeps its solves', function () {
    $student = teamPlayer();
    $solve = Solve::create(['team_id' => $student->team_id, 'challenge_id' => Challenge::factory()->create()->id, 'user_id' => $student->id, 'points' => 75]);

    $this->delete(route('admin.users.destroy', $student))->assertRedirect();
    $this->assertModelMissing($student);
    expect($solve->fresh())->user_id->toBeNull()->points->toBe(75);

    $this->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    $this->assertModelExists($this->admin);
});

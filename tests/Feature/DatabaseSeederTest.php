<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('seeding creates the instructor from config and updates it on rerun', function () {
    config(['ctf.instructor' => ['name' => 'Ms Rivera', 'email' => 'rivera@example.com', 'password' => 'first-Secret-1']]);
    $this->seed(DatabaseSeeder::class);

    config(['ctf.instructor.password' => 'second-Secret-2']);
    $this->seed(DatabaseSeeder::class);

    $instructor = User::where('email', 'rivera@example.com')->sole();
    expect($instructor->name)->toBe('Ms Rivera')
        ->and($instructor->email_verified_at)->not->toBeNull()
        ->and($instructor->isAdmin())->toBeTrue()
        ->and(Hash::check('second-Secret-2', $instructor->password))->toBeTrue();
});

test('seeding skips the instructor when no email or password is set', function () {
    config(['ctf.instructor' => ['name' => 'Instructor', 'email' => 'rivera@example.com', 'password' => '']]);

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 0);
});

<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ChallengeSeeder::class);
        $this->seedInstructor();

        // A full demo team for trying the game locally. The factory password is "password",
        // so these accounts must never exist on the live server.
        if (app()->isLocal()) {
            $this->seedDemoTeam();
        }
    }

    /**
     * test@example.com to test4@example.com in "Demo Team". Safe to rerun: existing accounts are reused.
     */
    private function seedDemoTeam(): void
    {
        $team = Team::firstOrCreate(['name' => 'Demo Team']);

        foreach (['test', 'test2', 'test3', 'test4'] as $index => $handle) {
            $member = User::where('email', "{$handle}@example.com")->first()
                ?? User::factory()->create(['name' => 'Test User '.($index + 1), 'email' => "{$handle}@example.com"]);

            if ($member->team_id === null && $team->members()->count() < Team::SIZE) {
                $member->forceFill(['team_id' => $team->id])->save();
            }
            $member->assignRole(User::STUDENT);
        }
    }

    /**
     * Create the instructor from .env, or update it on rerun so .env stays the source of truth.
     */
    private function seedInstructor(): void
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('ctf.instructor');

        if (blank($email) || blank($password)) {
            return;
        }

        $instructor = User::firstOrNew(['email' => $email]);

        // email_verified_at is not fillable, so forceFill; the instructor never needs a verification mail.
        $instructor->forceFill([
            'name' => $name,
            'password' => $password,
            'email_verified_at' => $instructor->email_verified_at ?? now(),
        ])->save();

        $instructor->syncRoles([User::ADMIN]);
    }
}

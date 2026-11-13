<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams play instead of single students. Solves and tries now belong to a team,
     * so the old per-student solves and attempts are dropped (they were test data).
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('email')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('attempts');
        Schema::dropIfExists('solves');

        Schema::create('solves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            // Who typed the winning flag; kept for display, cleared if that student is deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('points')->default(0);
            $table->timestamps();

            // One solve per team per challenge, enforced by the database.
            $table->unique(['team_id', 'challenge_id']);
        });

        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tries_used')->default(0);
            $table->boolean('hint_used')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'challenge_id']);
        });
    }

    /**
     * Back to per-student solves and attempts (empty).
     */
    public function down(): void
    {
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('solves');

        Schema::create('solves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tries_used')->default(0);
            $table->boolean('hint_used')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::dropIfExists('teams');
    }
};

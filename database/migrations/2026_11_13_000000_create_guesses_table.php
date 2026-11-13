<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every wrong flag a team tried, so teammates can see it and not waste a shared try on it again.
     */
    public function up(): void
    {
        Schema::create('guesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('flag', 200);
            $table->timestamps();

            $table->index(['team_id', 'challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guesses');
    }
};

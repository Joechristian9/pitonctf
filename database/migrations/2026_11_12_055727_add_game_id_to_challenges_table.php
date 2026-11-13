<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every challenge belongs to a game; numbers are now unique per game instead of site-wide.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Challenges from before games existed keep working: they move into one running game.
        if (DB::table('challenges')->exists()) {
            $gameId = DB::table('games')->insertGetId([
                'name' => 'PitonCTF 2026',
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('challenges')->update(['game_id' => $gameId]);
        }

        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable(false)->change();
            $table->dropUnique(['number']);
            $table->unique(['game_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            // MySQL keeps the unique index for the foreign key, so the key must go first.
            $table->dropForeign(['game_id']);
            $table->dropUnique(['game_id', 'number']);
            $table->dropColumn('game_id');
            $table->unique('number');
        });
    }
};

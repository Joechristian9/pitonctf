<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name students see on the scoreboard instead of the real team name.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('codename', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['codename']);
            $table->dropColumn('codename');
        });
    }
};

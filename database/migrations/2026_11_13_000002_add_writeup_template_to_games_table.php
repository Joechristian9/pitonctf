<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The write-up template the admin shares with every team; the file lives on the "ctf" disk.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('writeup_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('writeup_template');
        });
    }
};

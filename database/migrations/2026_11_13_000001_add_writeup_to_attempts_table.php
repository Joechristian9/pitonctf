<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A team's write-up (PDF or Word) for a challenge; the file itself lives on the private disk.
     */
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->string('writeup_path')->nullable();
            $table->string('writeup_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['writeup_path', 'writeup_name']);
        });
    }
};

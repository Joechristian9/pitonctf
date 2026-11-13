<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ScoreboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    // Old kit links and bookmarks still work.
    Route::redirect('dashboard', '/challenges')->name('dashboard');

    Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges');
    Route::post('challenges/{challenge}/submit', [ChallengeController::class, 'submit'])
        ->middleware('throttle:flag-submissions')
        ->name('challenges.submit');
    Route::post('challenges/{challenge}/hint', [ChallengeController::class, 'hint'])->name('challenges.hint');

    Route::get('scoreboard', [ScoreboardController::class, 'index'])->name('scoreboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ScoreboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    // Where every log-in lands: admins watch the scoreboard, students go play.
    Route::get('dashboard', fn (Request $request) => to_route($request->user()->isAdmin() ? 'scoreboard' : 'challenges'))->name('dashboard');

    Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges');
    Route::post('challenges/{challenge}/submit', [ChallengeController::class, 'submit'])
        ->middleware('throttle:flag-submissions')
        ->name('challenges.submit');
    Route::post('challenges/{challenge}/hint', [ChallengeController::class, 'hint'])
        ->middleware('throttle:hints')
        ->name('challenges.hint');
    Route::post('challenges/{challenge}/writeup', [ChallengeController::class, 'uploadWriteup'])
        ->middleware('throttle:writeups')
        ->name('challenges.writeup');
    Route::get('writeups/{attempt}', [ChallengeController::class, 'downloadWriteup'])->name('writeups.show');

    Route::get('scoreboard', [ScoreboardController::class, 'index'])->name('scoreboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

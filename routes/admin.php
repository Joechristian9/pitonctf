<?php

use App\Http\Controllers\Admin\ChallengeController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Old /admin bookmarks still work; admins manage challenges at /challenges.
    Route::redirect('/', '/challenges');
    Route::put('game', [GameController::class, 'update'])->name('game.update');
    Route::patch('game/status', [GameController::class, 'updateStatus'])->name('game.status');
    Route::delete('game/scores', [GameController::class, 'resetScores'])->name('game.scores.destroy');
    Route::post('game/writeup-template', [GameController::class, 'uploadWriteupTemplate'])->name('game.writeup-template');
    Route::delete('game/writeup-template', [GameController::class, 'removeWriteupTemplate'])->name('game.writeup-template.destroy');

    Route::resource('challenges', ChallengeController::class)->except(['index', 'show']);

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::patch('teams/{team}/codename', [TeamController::class, 'updateCodename'])->name('teams.codename');
    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::post('users/bulk', [UserController::class, 'storeMany'])->name('users.bulk');
    Route::post('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

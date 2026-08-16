<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\FixtureController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentRegistrationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('users', UserController::class)->except('show');
    Route::resource('players', PlayerController::class)->except('show');
    Route::resource('tournaments', TournamentController::class)->except('show');
    Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
    Route::get('tournaments/{tournament}/lists/{round}', [TournamentController::class, 'tableLists'])->name('tournaments.lists');

    Route::get('tournaments/{tournament}/register', [TournamentRegistrationController::class, 'index'])->name('tournaments.register');
    Route::post('tournaments/{tournament}/register', [TournamentRegistrationController::class, 'store'])->name('tournaments.register.store');
    Route::post('tournaments/{tournament}/register/join', [TournamentRegistrationController::class, 'join'])->name('tournaments.register.join');
    Route::delete('tournaments/{tournament}/register/players/{player}', [TournamentRegistrationController::class, 'destroyPlayer'])->name('tournaments.register.players.destroy');
    Route::delete('tournaments/{tournament}/register/teams/{team}', [TournamentRegistrationController::class, 'destroyTeam'])->name('tournaments.register.teams.destroy');

    Route::get('fixtures/{fixture}/edit', [FixtureController::class, 'edit'])->name('fixtures.edit');
    Route::put('fixtures/{fixture}', [FixtureController::class, 'update'])->name('fixtures.update');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('backups/{filename}', [BackupController::class, 'download'])->name('backups.download');
    Route::post('backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('users.impersonate');

    // Reachable by the impersonated session too, which is never admin.
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.destroy');
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('users', UserController::class)->except('show');
    Route::resource('players', PlayerController::class)->except('show');

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

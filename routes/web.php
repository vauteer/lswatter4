<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('users', UserController::class)->except('show');

    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('users.impersonate');

    // Reachable by the impersonated session too, which is never admin.
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.destroy');
});

require __DIR__.'/settings.php';

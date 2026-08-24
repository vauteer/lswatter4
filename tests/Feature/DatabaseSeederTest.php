<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds an admin user', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isBlocked())->toBeFalse();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the command updates an existing user\'s password', function () {
    $user = User::factory()->create();

    $this->artisan('app:set-password', ['email' => $user->email, 'password' => 'new-password'])
        ->expectsOutputToContain("Password updated for {$user->email}.")
        ->assertSuccessful();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('the command fails when no user has the given email', function () {
    $this->artisan('app:set-password', ['email' => 'missing@example.com', 'password' => 'new-password'])
        ->expectsOutputToContain('No user found with email missing@example.com.')
        ->assertFailed();
});

test('the command fails when the password does not meet the validation rules', function () {
    $user = User::factory()->create();

    $this->artisan('app:set-password', ['email' => $user->email, 'password' => 'short'])
        ->assertFailed();

    expect(Hash::check('short', $user->fresh()->password))->toBeFalse();
});

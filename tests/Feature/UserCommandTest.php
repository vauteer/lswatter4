<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the command creates a new user with a generated password', function () {
    $this->artisan('app:user', ['name' => 'New Player', 'email' => 'new-player@example.com'])
        ->expectsOutputToContain('Updated user New Player. The password is')
        ->assertSuccessful();

    $user = User::where('email', 'new-player@example.com')->firstOrFail();
    expect($user->name)->toBe('New Player');
});

test('the command creates a new user with an explicit password', function () {
    $this->artisan('app:user', [
        'name' => 'New Player',
        'email' => 'new-player@example.com',
        '--password' => 'a-chosen-password',
    ])
        ->expectsOutputToContain('Updated user New Player. The password is a-chosen-password')
        ->assertSuccessful();

    $user = User::where('email', 'new-player@example.com')->firstOrFail();
    expect(Hash::check('a-chosen-password', $user->password))->toBeTrue();
});

test('the command edits an existing user looked up by email, even when the name changes', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'existing@example.com']);
    $originalPassword = $user->password;

    $this->artisan('app:user', ['name' => 'New Name', 'email' => 'existing@example.com'])
        ->assertSuccessful();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->password)->toBe($originalPassword);
});

test('the command updates the password of an existing user without changing the name', function () {
    $user = User::factory()->create(['name' => 'Existing Player']);

    $this->artisan('app:user', [
        'name' => $user->name,
        'email' => $user->email,
        '--password' => 'a-new-password',
    ])->assertSuccessful();

    $user->refresh();
    expect(Hash::check('a-new-password', $user->password))->toBeTrue();
});

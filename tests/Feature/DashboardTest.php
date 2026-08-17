<?php

use App\Models\Player;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('non-admins cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertForbidden();
});

test('admins can visit the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard reports possible duplicate players, user stats, and backup status', function () {
    $admin = User::factory()->admin()->create();
    Player::factory()->create(['name' => 'Anna Müller']);
    Player::factory()->create(['name' => 'Anna Mueller']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('duplicatePlayerCount', 1)
        ->where('users.total', 1)
        ->where('lastBackup', null));
});

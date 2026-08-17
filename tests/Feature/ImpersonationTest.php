<?php

use App\Models\User;

test('admins can log in as another user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.impersonate', $user));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));
    $this->assertAuthenticatedAs($user);
    expect(session('impersonator_id'))->toBe($admin->id);
});

test('impersonating a user does not record it as a login', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('users.impersonate', $user));

    expect($user->fresh()->last_login_at)->toBeNull();
});

test('non-admins cannot log in as another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('users.impersonate', $other))->assertForbidden();
    $this->assertAuthenticatedAs($user);
});

test('admins cannot impersonate themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.impersonate', $admin))->assertNotFound();
});

test('admins cannot impersonate other admins', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.impersonate', $otherAdmin))->assertNotFound();
});

test('an impersonated session can return to the admin account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('users.impersonate', $user));

    $response = $this->delete(route('impersonate.destroy'));

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
    $this->assertAuthenticatedAs($admin);
    expect(session('impersonator_id'))->toBeNull();
});

test('returning to the admin account does not record it as a login', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('users.impersonate', $user));
    $this->delete(route('impersonate.destroy'));

    expect($admin->fresh()->last_login_at)->toBeNull();
});

test('returning to the admin account requires an active impersonation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('impersonate.destroy'))->assertNotFound();
});

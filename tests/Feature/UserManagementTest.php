<?php

use App\Models\User;

test('guests cannot access the users pages', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('non-admins cannot access the users pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($user)->get(route('users.create'))->assertForbidden();
});

test('admins can view the users list', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create();

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
});

test('admins can create a user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'admin' => '0',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'admin' => false,
    ]);
});

test('creating a user requires a unique email', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => $existing->email,
        'admin' => '0',
    ]);

    $response->assertSessionHasErrors('email');
});

test('admins can update a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'admin' => '1',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect($user->refresh()->name)->toBe('Updated Name');
    expect($user->admin)->toBeTrue();
});

test('admins can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect($user->fresh())->toBeNull();
});

test('admins cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

    $response->assertForbidden();
    expect($admin->fresh())->not->toBeNull();
});

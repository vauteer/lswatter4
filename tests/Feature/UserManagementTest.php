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

test('the users list reports null for a user who never logged in', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('users.index', ['search' => $user->email]));

    $response->assertInertia(fn ($page) => $page->where('users.data.0.last_login_at', null));
});

test('the users list reports when a user last logged in', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->forceFill(['last_login_at' => now()->subDay()])->save();

    $response = $this->actingAs($admin)->get(route('users.index', ['search' => $user->email]));

    $response->assertInertia(fn ($page) => $page->where(
        'users.data.0.last_login_at',
        fn (?string $value) => $value !== null,
    ));
});

test('the edit page reports which page and search to return to on cancel', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('users.edit', $user).'?page=2&search=jane');

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', 2)
        ->where('backSearch', 'jane'));
});

test('the edit page reports no page or search to return to when opened directly', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('users.edit', $user));

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', null)
        ->where('backSearch', null));
});

test('admins can create a user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'admin' => '0',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index', ['page' => 1]));

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

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index', ['page' => 1]));

    expect($user->refresh()->name)->toBe('Updated Name');
    expect($user->admin)->toBeTrue();
});

test('updating a user redirects to the page they appear on', function () {
    $admin = User::factory()->admin()->create(['name' => 'Aaa Admin']);
    for ($i = 1; $i <= 20; $i++) {
        User::factory()->create(['name' => sprintf('User %02d', $i)]);
    }
    $user = User::factory()->create(['name' => 'Zzz Target']);

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Zzz Target Updated',
        'email' => $user->email,
        'admin' => '0',
    ]);

    $response->assertRedirect(route('users.index', ['page' => 2]));
});

test('admins can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index', ['page' => 1]));

    expect($user->fresh())->toBeNull();
});

test('admins cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

    $response->assertForbidden();
    expect($admin->fresh())->not->toBeNull();
});

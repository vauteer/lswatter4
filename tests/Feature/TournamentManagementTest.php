<?php

use App\Models\Tournament;
use App\Models\User;

test('guests cannot access the tournaments pages', function () {
    $this->get(route('tournaments.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the tournaments list', function () {
    $user = User::factory()->create();
    Tournament::factory()->create();

    $response = $this->actingAs($user)->get(route('tournaments.index'));

    $response->assertOk();
});

test('authenticated users can create a tournament', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tournaments.store'), [
        'name' => 'Spring Open',
        'start' => '2026-03-01 10:00',
        'rounds' => 3,
        'games' => 4,
        'winpoints' => 11,
        'private' => false,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));

    $this->assertDatabaseHas('tournaments', [
        'name' => 'Spring Open',
        'created_by' => $user->id,
    ]);
});

test('creating a tournament requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tournaments.store'), [
        'name' => '',
        'start' => '2026-03-01 10:00',
        'rounds' => 3,
        'games' => 4,
        'winpoints' => 11,
    ]);

    $response->assertSessionHasErrors('name');
});

test('creators can update their own tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->put(route('tournaments.update', $tournament), [
        'name' => 'Updated Name',
        'start' => '2026-03-01 10:00',
        'rounds' => 3,
        'games' => 4,
        'winpoints' => 11,
        'private' => false,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));

    expect($tournament->refresh()->name)->toBe('Updated Name');
});

test('users cannot update a tournament created by someone else', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($user)->get(route('tournaments.edit', $tournament));

    $response->assertForbidden();
});

test('users cannot submit an update for a tournament created by someone else', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($user)->put(route('tournaments.update', $tournament), [
        'name' => 'Hijacked',
        'start' => '2026-03-01 10:00',
        'rounds' => 3,
        'games' => 4,
        'winpoints' => 11,
        'private' => false,
    ]);

    $response->assertForbidden();
    expect($tournament->fresh()->name)->not->toBe('Hijacked');
});

test('admins can update any tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($admin)->put(route('tournaments.update', $tournament), [
        'name' => 'Admin Update',
        'start' => '2026-03-01 10:00',
        'rounds' => 3,
        'games' => 4,
        'winpoints' => 11,
        'private' => false,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));

    expect($tournament->refresh()->name)->toBe('Admin Update');
});

test('creators can delete their own tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->delete(route('tournaments.destroy', $tournament));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));

    expect($tournament->fresh())->toBeNull();
});

test('users cannot delete a tournament created by someone else', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($user)->delete(route('tournaments.destroy', $tournament));

    $response->assertForbidden();
    expect($tournament->fresh())->not->toBeNull();
});

test('admins can delete any tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($admin)->delete(route('tournaments.destroy', $tournament));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index'));

    expect($tournament->fresh())->toBeNull();
});

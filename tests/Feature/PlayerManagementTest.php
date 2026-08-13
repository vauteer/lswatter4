<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\User;

test('guests cannot access the players pages', function () {
    $this->get(route('players.index'))->assertRedirect(route('login'));
});

test('non-admins cannot access the players pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('players.index'))->assertForbidden();
    $this->actingAs($user)->get(route('players.create'))->assertForbidden();
});

test('admins can view the players list', function () {
    $admin = User::factory()->admin()->create();
    Player::factory()->create();

    $response = $this->actingAs($admin)->get(route('players.index'));

    $response->assertOk();
});

test('admins can create a player', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('players.store'), [
        'name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index'));

    $this->assertDatabaseHas('players', [
        'name' => 'Jane Doe',
    ]);
});

test('creating a player requires a unique name', function () {
    $admin = User::factory()->admin()->create();
    $existing = Player::factory()->create();

    $response = $this->actingAs($admin)->post(route('players.store'), [
        'name' => $existing->name,
    ]);

    $response->assertSessionHasErrors('name');
});

test('admins can update a player', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();

    $response = $this->actingAs($admin)->put(route('players.update', $player), [
        'name' => 'Updated Name',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index'));

    expect($player->refresh()->name)->toBe('Updated Name');
});

test('admins can delete an unused player', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();

    $response = $this->actingAs($admin)->delete(route('players.destroy', $player));

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index'));

    expect($player->fresh())->toBeNull();
});

test('admins cannot delete a player who is part of a team', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();
    $partner = Player::factory()->create();
    Team::create(['player1_id' => $player->id, 'player2_id' => $partner->id]);

    $response = $this->actingAs($admin)->delete(route('players.destroy', $player));

    $response->assertForbidden();
    expect($player->fresh())->not->toBeNull();
});

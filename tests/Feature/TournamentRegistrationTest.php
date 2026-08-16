<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

test('guests cannot access the registration page', function () {
    $tournament = Tournament::factory()->create();

    $this->get(route('tournaments.register', $tournament))->assertRedirect(route('login'));
});

test('users cannot register participants for a tournament created by someone else', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $this->actingAs($user)->get(route('tournaments.register', $tournament))->assertForbidden();
    $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ])->assertForbidden();
});

test('creators can view the registration page for their own tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)->get(route('tournaments.register', $tournament))->assertOk();
});

test('a single player can be registered by name, creating the player on the fly', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));

    $player = Player::where('name', 'Jane Doe')->firstOrFail();
    expect($tournament->players()->whereKey($player->id)->exists())->toBeTrue();
});

test('a single player can be registered by picking an existing player', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->players()->whereKey($player->id)->exists())->toBeTrue();
});

test('two players registered together are stored as a team, reusing an existing pairing', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $team = Team::create(['player1_id' => $player1->id, 'player2_id' => $player2->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player1->id,
        'player2_id' => $player2->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));

    expect(Team::count())->toBe(1);
    expect($tournament->teams()->whereKey($team->id)->exists())->toBeTrue();
});

test('registering the same player twice for a tournament fails', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();
    $tournament->players()->attach($player);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player->id,
    ]);

    $response->assertSessionHasErrors('player1_id');
    expect($tournament->players()->count())->toBe(1);
});

test('two already individually registered players can be joined into a team', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->players()->attach([$player1->id, $player2->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.join', $tournament), [
        'player_ids' => [$player1->id, $player2->id],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));

    expect($tournament->players()->count())->toBe(0);
    expect($tournament->teams()->count())->toBe(1);

    $team = $tournament->teams()->first();
    expect([$team->player1_id, $team->player2_id])->toEqualCanonicalizing([$player1->id, $player2->id]);
});

test('joining players who are not both individually registered fails', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->players()->attach($player1);

    $response = $this->actingAs($user)->post(route('tournaments.register.join', $tournament), [
        'player_ids' => [$player1->id, $player2->id],
    ]);

    $response->assertSessionHasErrors('player_ids');
    expect($tournament->teams()->count())->toBe(0);
});

test('a registered single player can be unregistered', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();
    $tournament->players()->attach($player);

    $response = $this->actingAs($user)->delete(route('tournaments.register.players.destroy', [$tournament, $player]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->players()->count())->toBe(0);
});

test('a registered team can be unregistered', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $team = Team::factory()->create();
    $tournament->teams()->attach($team);

    $response = $this->actingAs($user)->delete(route('tournaments.register.teams.destroy', [$tournament, $team]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->teams()->count())->toBe(0);
});

test('admins can register participants for any tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($admin)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors();
});

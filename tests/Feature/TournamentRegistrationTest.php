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

test('the registration page lists the ids of every already registered player', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $single = Player::factory()->create();
    $team = Team::factory()->create();
    $unregistered = Player::factory()->create();
    $tournament->singlePlayers()->attach($single);
    $tournament->teams()->attach($team);

    $this->actingAs($user)->get(route('tournaments.register', $tournament))
        ->assertInertia(fn ($page) => $page
            ->where('registeredPlayerIds', fn ($ids) => collect($ids)->sort()->values()->all()
                === collect([$single->id, $team->player1_id, $team->player2_id])->sort()->values()->all())
            ->where('allPlayers', fn ($players) => collect($players)->pluck('id')->contains($unregistered->id))
        );
});

test('a single player can be registered by name, creating the player on the fly', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));

    $player = Player::where('name', 'Jane Doe')->firstOrFail();
    expect($tournament->singlePlayers()->whereKey($player->id)->exists())->toBeTrue();
});

test('a single player can be registered by picking an existing player', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->singlePlayers()->whereKey($player->id)->exists())->toBeTrue();
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
    $tournament->singlePlayers()->attach($player);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player->id,
    ]);

    $response->assertSessionHasErrors('player1_id');
    expect($tournament->singlePlayers()->count())->toBe(1);
});

test('two already individually registered players can be joined into a team', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->singlePlayers()->attach([$player1->id, $player2->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.join', $tournament), [
        'player_ids' => [$player1->id, $player2->id],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));

    expect($tournament->singlePlayers()->count())->toBe(0);
    expect($tournament->teams()->count())->toBe(1);

    $team = $tournament->teams()->first();
    expect([$team->player1_id, $team->player2_id])->toEqualCanonicalizing([$player1->id, $player2->id]);
});

test('joining players who are not both individually registered fails', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->singlePlayers()->attach($player1);

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
    $tournament->singlePlayers()->attach($player);

    $response = $this->actingAs($user)->delete(route('tournaments.register.players.destroy', [$tournament, $player]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->singlePlayers()->count())->toBe(0);
});

test('unregistering a player who was never used elsewhere deletes them', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();
    $tournament->singlePlayers()->attach($player);

    $this->actingAs($user)->delete(route('tournaments.register.players.destroy', [$tournament, $player]))
        ->assertSessionHasNoErrors();

    expect(Player::find($player->id))->toBeNull();
});

test('unregistering a player who is still used elsewhere keeps them', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $player = Player::factory()->create();
    $tournament->singlePlayers()->attach($player);
    Tournament::factory()->create()->singlePlayers()->attach($player);

    $this->actingAs($user)->delete(route('tournaments.register.players.destroy', [$tournament, $player]))
        ->assertSessionHasNoErrors();

    expect(Player::find($player->id))->not->toBeNull();
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

test('unregistering a team whose players were never used elsewhere deletes the team and both players', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $team = Team::factory()->create();
    $tournament->teams()->attach($team);

    $this->actingAs($user)->delete(route('tournaments.register.teams.destroy', [$tournament, $team]))
        ->assertSessionHasNoErrors();

    expect(Team::find($team->id))->toBeNull()
        ->and(Player::find($team->player1_id))->toBeNull()
        ->and(Player::find($team->player2_id))->toBeNull();
});

test('unregistering a team keeps a player who is still used elsewhere', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $team = Team::factory()->create();
    $tournament->teams()->attach($team);
    Tournament::factory()->create()->singlePlayers()->attach($team->player1_id);

    $this->actingAs($user)->delete(route('tournaments.register.teams.destroy', [$tournament, $team]))
        ->assertSessionHasNoErrors();

    expect(Player::find($team->player1_id))->not->toBeNull()
        ->and(Player::find($team->player2_id))->toBeNull();
});

test('unregistering a team still registered for another tournament keeps the team and its players', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    $team = Team::factory()->create();
    $tournament->teams()->attach($team);
    Tournament::factory()->create()->teams()->attach($team);

    $this->actingAs($user)->delete(route('tournaments.register.teams.destroy', [$tournament, $team]))
        ->assertSessionHasNoErrors();

    expect(Team::find($team->id))->not->toBeNull()
        ->and(Player::find($team->player1_id))->not->toBeNull()
        ->and(Player::find($team->player2_id))->not->toBeNull();
});

test('admins can register participants for any tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($admin)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors();
});

function startedTournament(User $user): Tournament
{
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }

    $tournament->draw();
    $tournament->fixtures()->first()->update(['score' => '11-5 11-5 11-5 11-5']);

    return $tournament;
}

test('the registration page reports registration as closed once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = startedTournament($user);

    $this->actingAs($user)->get(route('tournaments.register', $tournament))
        ->assertInertia(fn ($page) => $page->where('tournament.registrationOpen', false));
});

test('registering a player is rejected once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = startedTournament($user);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertStatus(422);
});

test('joining players is rejected once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = startedTournament($user);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->singlePlayers()->attach([$player1->id, $player2->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.join', $tournament), [
        'player_ids' => [$player1->id, $player2->id],
    ]);

    $response->assertStatus(422);
});

test('unregistering a player is rejected once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = startedTournament($user);
    $player = Player::factory()->create();
    $tournament->singlePlayers()->attach($player);

    $response = $this->actingAs($user)->delete(route('tournaments.register.players.destroy', [$tournament, $player]));

    $response->assertStatus(422);
    expect($tournament->singlePlayers()->count())->toBe(1);
});

test('unregistering a team is rejected once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = startedTournament($user);
    $team = $tournament->teams()->first();

    $response = $this->actingAs($user)->delete(route('tournaments.register.teams.destroy', [$tournament, $team]));

    $response->assertStatus(422);
    expect($tournament->teams()->whereKey($team->id)->exists())->toBeTrue();
});

function drawnTournamentFor(User $user): Tournament
{
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }

    $tournament->draw();

    return $tournament;
}

test('registering a single player after the draw discards it, with a note on the toast', function () {
    $user = User::factory()->create();
    $tournament = drawnTournamentFor($user);

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.register', $tournament));
    expect($tournament->drawn())->toBeFalse();
    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'success',
        'message' => __(':name registered.', ['name' => 'Jane Doe']).' '.__('The existing draw was discarded because the roster changed.'),
    ]);
});

test('registering a team after the draw discards it', function () {
    $user = User::factory()->create();
    $tournament = drawnTournamentFor($user);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();

    $response = $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'player1_id' => $player1->id,
        'player2_id' => $player2->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect($tournament->drawn())->toBeFalse();
});

test('joining players into a team after the draw discards it', function () {
    $user = User::factory()->create();
    $tournament = drawnTournamentFor($user);
    $player1 = Player::factory()->create();
    $player2 = Player::factory()->create();
    $tournament->singlePlayers()->attach([$player1->id, $player2->id]);

    $response = $this->actingAs($user)->post(route('tournaments.register.join', $tournament), [
        'player_ids' => [$player1->id, $player2->id],
    ]);

    $response->assertSessionHasNoErrors();
    expect($tournament->drawn())->toBeFalse();
    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'success',
        'message' => __(':player1 and :player2 joined into a team.', [
            'player1' => $player1->name,
            'player2' => $player2->name,
        ]).' '.__('The existing draw was discarded because the roster changed.'),
    ]);
});

test('registering a player before any draw does not mention a discarded draw', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)->post(route('tournaments.register.store', $tournament), [
        'new_player1_name' => 'Jane Doe',
    ]);

    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'success',
        'message' => __(':name registered.', ['name' => 'Jane Doe']),
    ]);
});

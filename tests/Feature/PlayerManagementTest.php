<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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

test('the edit page reports which page and search to return to on cancel', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();

    $response = $this->actingAs($admin)->get(route('players.edit', $player).'?page=3&search=foo');

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', 3)
        ->where('backSearch', 'foo'));
});

test('the edit page reports no page or search to return to when opened directly', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();

    $response = $this->actingAs($admin)->get(route('players.edit', $player));

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', null)
        ->where('backSearch', null));
});

test('admins can create a player', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('players.store'), [
        'name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index', ['page' => 1]));

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

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index', ['page' => 1]));

    expect($player->refresh()->name)->toBe('Updated Name');
});

test('editing a player redirects to the page it appears on', function () {
    $admin = User::factory()->admin()->create();
    for ($i = 1; $i <= 20; $i++) {
        Player::factory()->create(['name' => sprintf('Player %02d', $i)]);
    }
    $player = Player::factory()->create(['name' => 'Player 21']);

    $response = $this->actingAs($admin)->put(route('players.update', $player), [
        'name' => 'Player 21 Updated',
    ]);

    $response->assertRedirect(route('players.index', ['page' => 2]));
});

test('admins can delete an unused player', function () {
    $admin = User::factory()->admin()->create();
    $player = Player::factory()->create();

    $response = $this->actingAs($admin)->delete(route('players.destroy', $player));

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index', ['page' => 1]));

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

test('possible duplicate groups catch names entered under slightly different spelling', function () {
    Player::factory()->create(['name' => 'Max Mustermann']);
    Player::factory()->create(['name' => 'max  mustermann ']);
    Player::factory()->create(['name' => 'Müller Hans']);
    Player::factory()->create(['name' => 'Mueller Hans']);
    Player::factory()->create(['name' => 'Completely Different']);

    $groups = Player::possibleDuplicateGroups();

    expect($groups)->toHaveCount(2);
    expect($groups->map(fn ($group) => $group->pluck('name')->sort()->values()->all())->all())
        ->toContain(['Max Mustermann', 'max  mustermann '])
        ->toContain(['Mueller Hans', 'Müller Hans']);
});

test('possible duplicate groups catch firstname and surname entered in swapped order', function () {
    Player::factory()->create(['name' => 'Bärbel Schindler']);
    Player::factory()->create(['name' => 'Schindler Bärbel']);
    Player::factory()->create(['name' => 'Completely Different']);

    $groups = Player::possibleDuplicateGroups();

    expect($groups)->toHaveCount(1);
    expect($groups->map(fn ($group) => $group->pluck('name')->sort()->values()->all())->all())
        ->toContain(['Bärbel Schindler', 'Schindler Bärbel']);
});

test('possible duplicate groups catch a common German nickname for the same first name', function () {
    Player::factory()->create(['name' => 'Spänle Heinrich']);
    Player::factory()->create(['name' => 'Spänle Heiner']);
    Player::factory()->create(['name' => 'Completely Different']);

    $groups = Player::possibleDuplicateGroups();

    expect($groups)->toHaveCount(1);
    expect($groups->map(fn ($group) => $group->pluck('name')->sort()->values()->all())->all())
        ->toContain(['Spänle Heiner', 'Spänle Heinrich']);
});

test('possible duplicate groups do not flag clearly different names', function () {
    Player::factory()->create(['name' => 'Anna Schmidt']);
    Player::factory()->create(['name' => 'Ben Fischer']);

    expect(Player::possibleDuplicateGroups())->toBeEmpty();
});

test('the players index page reports possible duplicate groups', function () {
    $admin = User::factory()->admin()->create();
    Player::factory()->create(['name' => 'Max Mustermann']);
    Player::factory()->create(['name' => 'Max Musterman']);

    $response = $this->actingAs($admin)->get(route('players.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('duplicateGroups', 1)
        ->has('duplicateGroups.0', 2));
});

test('merging a player reassigns their individual tournament registration', function () {
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();
    $tournament = Tournament::factory()->create();
    $tournament->singlePlayers()->attach($duplicate);

    $keeper->mergeWith($duplicate);

    expect($tournament->singlePlayers()->whereKey($keeper->id)->exists())->toBeTrue();
    expect(Player::find($duplicate->id))->toBeNull();
});

test('merging a player reassigns their team membership', function () {
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();
    $partner = Player::factory()->create();
    $team = Team::create(['player1_id' => $duplicate->id, 'player2_id' => $partner->id]);

    $keeper->mergeWith($duplicate);

    expect($team->fresh()->player1_id)->toBe($keeper->id);
    expect(Player::find($duplicate->id))->toBeNull();
});

test('merging is rejected when both players are individually registered for the same tournament', function () {
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();
    $tournament = Tournament::factory()->create();
    $tournament->singlePlayers()->attach([$keeper->id, $duplicate->id]);

    expect(fn () => $keeper->mergeWith($duplicate))
        ->toThrow(ValidationException::class);

    expect(Player::find($duplicate->id))->not->toBeNull();
});

test('merging is rejected when one player is registered individually and the other via a team in the same tournament', function () {
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();
    $partner = Player::factory()->create();
    $team = Team::create(['player1_id' => $duplicate->id, 'player2_id' => $partner->id]);
    $tournament = Tournament::factory()->create();
    $tournament->singlePlayers()->attach($keeper);
    $tournament->teams()->attach($team);

    expect(fn () => $keeper->mergeWith($duplicate))
        ->toThrow(ValidationException::class);
});

test('admins can merge duplicate players via the players.merge endpoint', function () {
    $admin = User::factory()->admin()->create();
    $keeper = Player::factory()->create(['name' => 'Max Mustermann']);
    $duplicate = Player::factory()->create(['name' => 'Max Musterman']);

    $response = $this->actingAs($admin)->post(route('players.merge'), [
        'keep_id' => $keeper->id,
        'player_ids' => [$keeper->id, $duplicate->id],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('players.index'));
    expect(Player::find($duplicate->id))->toBeNull();
    expect(Player::find($keeper->id))->not->toBeNull();
});

test('merging duplicate players also consolidates any teams that now pair them with the same partner', function () {
    $admin = User::factory()->admin()->create();
    $keeper = Player::factory()->create(['name' => 'Spänle Heinrich']);
    $duplicate = Player::factory()->create(['name' => 'Spänle Heiner']);
    $partner = Player::factory()->create();

    $teamViaKeeper = Team::create(['player1_id' => $keeper->id, 'player2_id' => $partner->id]);
    $teamViaDuplicate = Team::create(['player1_id' => $duplicate->id, 'player2_id' => $partner->id]);

    $this->actingAs($admin)->post(route('players.merge'), [
        'keep_id' => $keeper->id,
        'player_ids' => [$keeper->id, $duplicate->id],
    ]);

    expect(Team::find($teamViaKeeper->id))->not->toBeNull();
    expect(Team::find($teamViaDuplicate->id))->toBeNull();
});

test('merging more than two players at once merges all of them into the keeper', function () {
    $admin = User::factory()->admin()->create();
    $keeper = Player::factory()->create();
    $duplicate1 = Player::factory()->create();
    $duplicate2 = Player::factory()->create();

    $response = $this->actingAs($admin)->post(route('players.merge'), [
        'keep_id' => $keeper->id,
        'player_ids' => [$keeper->id, $duplicate1->id, $duplicate2->id],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Player::find($duplicate1->id))->toBeNull();
    expect(Player::find($duplicate2->id))->toBeNull();
    expect(Player::find($keeper->id))->not->toBeNull();
});

test('merging via the endpoint requires the keeper to be one of the selected players', function () {
    $admin = User::factory()->admin()->create();
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();
    $other = Player::factory()->create();

    $response = $this->actingAs($admin)->post(route('players.merge'), [
        'keep_id' => $other->id,
        'player_ids' => [$keeper->id, $duplicate->id],
    ]);

    $response->assertSessionHasErrors('keep_id');
    expect(Player::find($duplicate->id))->not->toBeNull();
});

test('merging via the endpoint rolls back entirely when a conflict is found partway through', function () {
    $admin = User::factory()->admin()->create();
    $keeper = Player::factory()->create();
    $safeDuplicate = Player::factory()->create();
    $conflictingDuplicate = Player::factory()->create();
    $tournament = Tournament::factory()->create();
    $tournament->singlePlayers()->attach([$keeper->id, $conflictingDuplicate->id]);

    $response = $this->actingAs($admin)->post(route('players.merge'), [
        'keep_id' => $keeper->id,
        'player_ids' => [$keeper->id, $safeDuplicate->id, $conflictingDuplicate->id],
    ]);

    $response->assertSessionHasErrors('player_ids');
    expect(Player::find($safeDuplicate->id))->not->toBeNull();
    expect(Player::find($conflictingDuplicate->id))->not->toBeNull();
});

test('non-admins cannot merge players', function () {
    $user = User::factory()->create();
    $keeper = Player::factory()->create();
    $duplicate = Player::factory()->create();

    $response = $this->actingAs($user)->post(route('players.merge'), [
        'keep_id' => $keeper->id,
        'player_ids' => [$keeper->id, $duplicate->id],
    ]);

    $response->assertForbidden();
    expect(Player::find($duplicate->id))->not->toBeNull();
});

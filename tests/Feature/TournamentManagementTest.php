<?php

use App\Models\Team;
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

test('the tournaments list reports whether registration is still open for each tournament', function () {
    $admin = User::factory()->admin()->create();
    $open = Tournament::factory()->create(['start' => now()->addDays(2)]);
    $started = Tournament::factory()->create(['start' => now()->addDay()]);
    for ($i = 0; $i < 4; $i++) {
        $started->teams()->attach(Team::factory()->create());
    }
    $started->draw();
    $started->fixtures()->first()->update(['score' => '11-5 11-5 11-5 11-5']);

    $response = $this->actingAs($admin)->get(route('tournaments.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('tournaments.data.0.id', $open->id)
        ->where('tournaments.data.0.registrationOpen', true)
        ->where('tournaments.data.1.id', $started->id)
        ->where('tournaments.data.1.registrationOpen', false));
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

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));

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

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));

    expect($tournament->refresh()->name)->toBe('Updated Name');
});

test('updating a tournament redirects to the page it appears on', function () {
    $admin = User::factory()->admin()->create();
    for ($i = 1; $i <= 20; $i++) {
        Tournament::factory()->create(['start' => now()->addDays(21 - $i)]);
    }
    $tournament = Tournament::factory()->create(['start' => now(), 'created_by' => $admin->id]);

    $response = $this->actingAs($admin)->put(route('tournaments.update', $tournament), [
        'name' => 'Updated',
        'start' => $tournament->start->format('Y-m-d H:i'),
        'rounds' => $tournament->rounds,
        'games' => $tournament->games,
        'winpoints' => $tournament->winpoints,
        'private' => $tournament->private,
    ]);

    $response->assertRedirect(route('tournaments.index', ['page' => 2]));
});

test('the edit page reports which page and search to return to on cancel', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('tournaments.edit', $tournament).'?page=2&search=open');

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', 2)
        ->where('backSearch', 'open'));
});

test('the edit page reports no page or search to return to when opened directly', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('tournaments.edit', $tournament));

    $response->assertInertia(fn ($page) => $page
        ->where('backPage', null)
        ->where('backSearch', null));
});

test('the edit page reports whether the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)->get(route('tournaments.edit', $tournament))
        ->assertInertia(fn ($page) => $page->where('started', false));
});

test('rounds, games, and winpoints can still be changed on a drawn but not yet started tournament, discarding the draw', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id, 'rounds' => 3, 'games' => 4, 'winpoints' => 11]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();

    $response = $this->actingAs($user)->put(route('tournaments.update', $tournament), [
        'name' => $tournament->name,
        'start' => $tournament->start->format('Y-m-d H:i'),
        'rounds' => 4,
        'games' => $tournament->games,
        'winpoints' => $tournament->winpoints,
        'private' => $tournament->private,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));
    expect($tournament->refresh()->rounds)->toBe(4);
    expect($tournament->drawn())->toBeFalse();
    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'success',
        'message' => __('Tournament updated.').' '.__('The existing draw was discarded because the tournament format changed.'),
    ]);
});

test('rounds, games, and winpoints cannot be changed once the tournament has started', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id, 'rounds' => 3, 'games' => 4, 'winpoints' => 11]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();
    $tournament->fixtures()->first()->update(['score' => '11-5 11-5 11-5 11-5']);

    $response = $this->actingAs($user)->put(route('tournaments.update', $tournament), [
        'name' => $tournament->name,
        'start' => $tournament->start->format('Y-m-d H:i'),
        'rounds' => 5,
        'games' => $tournament->games,
        'winpoints' => $tournament->winpoints,
        'private' => $tournament->private,
    ]);

    $response->assertSessionHasErrors('rounds');
    expect($tournament->fresh()->rounds)->toBe(3);
});

test('other fields can still be updated once the tournament has started, as long as the format is unchanged', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id, 'rounds' => 3, 'games' => 4, 'winpoints' => 11]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();
    $tournament->fixtures()->first()->update(['score' => '11-5 11-5 11-5 11-5']);

    $response = $this->actingAs($user)->put(route('tournaments.update', $tournament), [
        'name' => 'Renamed Tournament',
        'start' => $tournament->start->format('Y-m-d H:i'),
        'rounds' => $tournament->rounds,
        'games' => $tournament->games,
        'winpoints' => $tournament->winpoints,
        'private' => $tournament->private,
    ]);

    $response->assertSessionHasNoErrors();
    expect($tournament->refresh()->name)->toBe('Renamed Tournament');
    expect($tournament->drawn())->toBeTrue();
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

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));

    expect($tournament->refresh()->name)->toBe('Admin Update');
});

test('creators can delete their own tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->delete(route('tournaments.destroy', $tournament));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));

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

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.index', ['page' => 1]));

    expect($tournament->fresh())->toBeNull();
});

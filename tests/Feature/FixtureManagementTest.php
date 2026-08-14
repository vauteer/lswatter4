<?php

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

function createFixture(array $tournamentAttributes = []): Fixture
{
    $tournament = Tournament::factory()->create([
        'games' => 3,
        'winpoints' => 11,
        ...$tournamentAttributes,
    ]);

    return Fixture::factory()->create([
        'tournament_id' => $tournament->id,
        'team1_id' => Team::factory(),
        'team2_id' => Team::factory(),
    ]);
}

test('guests cannot edit a fixture result', function () {
    $fixture = createFixture();

    $this->get(route('fixtures.edit', $fixture))->assertRedirect(route('login'));
});

test('the tournament creator can edit a fixture result', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('fixtures.edit', $fixture));

    $response->assertOk();
});

test('other users cannot edit a fixture result', function () {
    $user = User::factory()->create();
    $fixture = createFixture();

    $response = $this->actingAs($user)->get(route('fixtures.edit', $fixture));

    $response->assertForbidden();
});

test('admins can edit any fixture result', function () {
    $admin = User::factory()->admin()->create();
    $fixture = createFixture();

    $response = $this->actingAs($admin)->get(route('fixtures.edit', $fixture));

    $response->assertOk();
});

test('a valid score updates the fixture', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    $response = $this->actingAs($user)->put(route('fixtures.update', $fixture), [
        'score' => '11-5 8-11 11-9',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('tournaments.show', [
            'tournament' => $fixture->tournament_id,
            'round' => $fixture->round,
        ]));

    $fixture->refresh();
    expect($fixture->score)->toBe('11-5 8-11 11-9');
    expect($fixture->team1_won)->toBe(2);
    expect($fixture->team2_won)->toBe(1);
});

test('a score with the wrong number of games is rejected', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    $response = $this->actingAs($user)->put(route('fixtures.update', $fixture), [
        'score' => '11-5 8-11',
    ]);

    $response->assertSessionHasErrors('score');
    expect($fixture->fresh()->score)->toBeNull();
});

test('other users cannot update a fixture result', function () {
    $user = User::factory()->create();
    $fixture = createFixture();

    $response = $this->actingAs($user)->put(route('fixtures.update', $fixture), [
        'score' => '11-5 8-11 11-9',
    ]);

    $response->assertForbidden();
    expect($fixture->fresh()->score)->toBeNull();
});

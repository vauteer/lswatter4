<?php

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Carbon;

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
    $response->assertInertia(fn ($page) => $page
        ->component('fixtures/Edit')
        ->where('gamesNeeded', 3));
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

test('a result can still be edited within 24 hours of the tournament finishing', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $fixture->calculate('11-5 8-11 11-9', true);

    Carbon::setTestNow('2026-01-02 09:59:59');
    $response = $this->actingAs($user)->put(route('fixtures.update', $fixture), [
        'score' => '11-5 8-11 11-7',
    ]);

    $response->assertSessionHasNoErrors();
    expect($fixture->fresh()->score)->toBe('11-5 8-11 11-7');
});

test('a result can no longer be edited more than 24 hours after the tournament finished', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $fixture->calculate('11-5 8-11 11-9', true);

    Carbon::setTestNow('2026-01-02 10:00:01');
    $this->actingAs($user)->get(route('fixtures.edit', $fixture))->assertForbidden();

    $response = $this->actingAs($user)->put(route('fixtures.update', $fixture), [
        'score' => '11-5 8-11 11-7',
    ]);

    $response->assertForbidden();
    expect($fixture->fresh()->score)->toBe('11-5 8-11 11-9');
});

test('admins also cannot edit results more than 24 hours after the tournament finished', function () {
    $admin = User::factory()->admin()->create();
    $fixture = createFixture();

    Carbon::setTestNow('2026-01-01 10:00:00');
    $fixture->calculate('11-5 8-11 11-9', true);

    Carbon::setTestNow('2026-01-02 10:00:01');
    $this->actingAs($admin)->get(route('fixtures.edit', $fixture))->assertForbidden();
});

test('the show page marks a locked fixture as not editable', function () {
    $user = User::factory()->create();
    $fixture = createFixture(['created_by' => $user->id]);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $fixture->calculate('11-5 8-11 11-9', true);

    Carbon::setTestNow('2026-01-02 10:00:01');
    $response = $this->actingAs($user)->get(route('tournaments.show', [
        'tournament' => $fixture->tournament_id,
        'round' => $fixture->round,
    ]));

    $response->assertInertia(fn ($page) => $page->where('fixtures.data.0.editable', false));
});

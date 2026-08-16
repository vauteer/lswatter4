<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function registerTeams(Tournament $tournament, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
}

test('a tournament with fewer than four teams cannot be drawn', function () {
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 2);

    expect($tournament->canDraw())->toBeFalse();
});

test('a tournament with an odd number of teams cannot be drawn', function () {
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 5);

    expect($tournament->canDraw())->toBeFalse();
});

test('a tournament with single players still registered cannot be drawn', function () {
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 4);
    $tournament->players()->attach(Player::factory()->create());

    expect($tournament->canDraw())->toBeFalse();
});

test('a tournament with four or more teams, evenly matched, and no single players can be drawn', function () {
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 4);

    expect($tournament->canDraw())->toBeTrue();
});

test('drawing creates one fixture per table per round', function () {
    $tournament = Tournament::factory()->create(['rounds' => 3]);
    registerTeams($tournament, 6);

    $tournament->draw();

    expect($tournament->fixtures()->count())->toBe(3 * 3);
});

test('team1 stays at the same table for every round while team2 moves to the next table', function () {
    $tournament = Tournament::factory()->create(['rounds' => 3]);
    registerTeams($tournament, 6);

    $tournament->draw();

    $byTable = $tournament->fixtures()->orderBy('round')->get()->groupBy('table_number');

    foreach ($byTable as $fixturesAtTable) {
        expect($fixturesAtTable->pluck('team1_id')->unique())->toHaveCount(1);
    }

    $team2ByRound = $tournament->fixtures()->orderBy('round')->get()->groupBy('round')
        ->map(fn ($fixtures) => $fixtures->pluck('team2_id', 'table_number'));

    foreach (range(1, 2) as $round) {
        foreach ($team2ByRound[$round] as $table => $teamId) {
            $nextTable = $table === 3 ? 1 : $table + 1;
            expect($team2ByRound[$round + 1][$nextTable])->toBe($teamId);
        }
    }
});

test('guests cannot draw a tournament', function () {
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 4);

    $this->post(route('tournaments.draw', $tournament))->assertRedirect(route('login'));
});

test('users cannot draw a tournament created by someone else', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    registerTeams($tournament, 4);

    $this->actingAs($user)->post(route('tournaments.draw', $tournament))->assertForbidden();
    expect($tournament->drawn())->toBeFalse();
});

test('creators can draw their own tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    registerTeams($tournament, 4);

    $response = $this->actingAs($user)->post(route('tournaments.draw', $tournament));

    $response->assertSessionHasNoErrors()->assertRedirect(route('tournaments.show', $tournament));
    expect($tournament->drawn())->toBeTrue();
});

test('drawing when the roster is not ready is rejected', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    registerTeams($tournament, 3);

    $response = $this->actingAs($user)->post(route('tournaments.draw', $tournament));

    $response->assertStatus(422);
    expect($tournament->drawn())->toBeFalse();
});

test('the registration page exposes whether the tournament can be drawn', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    registerTeams($tournament, 4);

    $this->actingAs($user)->get(route('tournaments.register', $tournament))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tournaments/Register')
            ->where('canDraw', true)
            ->where('drawn', false));
});

test('redrawing replaces existing fixtures', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    registerTeams($tournament, 4);
    $tournament->draw();
    $originalFixtureIds = $tournament->fixtures()->pluck('id');

    $response = $this->actingAs($user)->post(route('tournaments.draw', $tournament));

    $response->assertSessionHasNoErrors();
    $newFixtureIds = $tournament->fixtures()->pluck('id');
    expect($newFixtureIds->intersect($originalFixtureIds))->toBeEmpty();
    expect($tournament->fixtures()->count())->toBe($originalFixtureIds->count());
});

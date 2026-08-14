<?php

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

test('guests cannot view a tournament', function () {
    $tournament = Tournament::factory()->create();

    $this->get(route('tournaments.show', $tournament))->assertRedirect(route('login'));
});

test('authenticated users can view a tournament with its fixtures and standings', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 2, 'start' => now()->subDay()]);
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();
    $tournament->teams()->attach([$team1->id, $team2->id]);
    Fixture::factory()->create([
        'tournament_id' => $tournament->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'round' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tournaments/Show')
        ->where('currentRound', 2)
        ->has('fixtures.data', 1)
        ->has('standings', 2)
    );
});

test('the table lists PDF can be downloaded by authenticated users', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['start' => now()->subDay()]);
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();
    Fixture::factory()->create([
        'tournament_id' => $tournament->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'round' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('tournaments.lists', ['tournament' => $tournament, 'round' => 1]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

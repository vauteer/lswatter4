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

test('a tournament that has not been drawn yet defaults to round 1', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 3, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

    $response->assertInertia(fn ($page) => $page->where('currentRound', 1));
});

test('the show page defaults to the first round with an unscored fixture', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 3, 'created_by' => $user->id]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();
    $tournament->fixtures()->where('round', 1)->each(fn (Fixture $fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

    $response->assertInertia(fn ($page) => $page->where('currentRound', 2));
});

test('an explicit round query parameter overrides the default', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 3, 'created_by' => $user->id]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();

    $response = $this->actingAs($user)->get(route('tournaments.show', ['tournament' => $tournament, 'round' => 3]));

    $response->assertInertia(fn ($page) => $page->where('currentRound', 3));
});

test('a fully scored tournament defaults to the last round', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 2, 'created_by' => $user->id]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();
    $tournament->fixtures()->each(fn (Fixture $fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

    $response->assertInertia(fn ($page) => $page->where('currentRound', 2));
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

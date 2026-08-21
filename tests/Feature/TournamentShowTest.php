<?php

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

test('guests cannot view a tournament that has not started yet', function () {
    $tournament = Tournament::factory()->create(['start' => now()->addDay()]);
    Fixture::factory()->create(['tournament_id' => $tournament->id, 'round' => 1]);

    $this->get(route('tournaments.show', $tournament))->assertForbidden();
});

test('guests can view a tournament that has already started', function () {
    $tournament = Tournament::factory()->create(['start' => now()->subDay()]);
    Fixture::factory()->create(['tournament_id' => $tournament->id, 'round' => 1]);

    $this->get(route('tournaments.show', $tournament))->assertOk();
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

test('a tournament that has not been drawn yet has no show page', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['rounds' => 3, 'created_by' => $user->id]);

    $this->actingAs($user)->get(route('tournaments.show', $tournament))->assertNotFound();
});

test('the show page disappears again when the draw is discarded', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['created_by' => $user->id]);
    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }
    $tournament->draw();

    $this->actingAs($user)->get(route('tournaments.show', $tournament))->assertOk();

    $tournament->discardDraw();

    $this->actingAs($user)->get(route('tournaments.show', $tournament))->assertNotFound();
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
    $response->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('inline')
        ->toContain('Tischlisten Runde 1');
});

test('guests cannot download the table lists PDF', function () {
    $tournament = Tournament::factory()->create(['start' => now()->subDay()]);

    $response = $this->get(route('tournaments.lists', ['tournament' => $tournament, 'round' => 1]));

    $response->assertRedirect(route('login'));
});

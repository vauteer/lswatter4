<?php

use App\Models\Team;
use App\Models\Tournament;
use App\TournamentState;

function drawnTournament(int $rounds = 2): Tournament
{
    $tournament = Tournament::factory()->create(['rounds' => $rounds]);

    for ($i = 0; $i < 4; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }

    $tournament->draw();

    return $tournament;
}

test('a tournament that has not been drawn is registering', function () {
    $tournament = Tournament::factory()->create();

    expect($tournament->state())->toBe(TournamentState::Registering);
});

test('a tournament that has been drawn but has no results yet is drawn', function () {
    $tournament = drawnTournament();

    expect($tournament->state())->toBe(TournamentState::Drawn);
});

test('a tournament with at least one but not all results entered is running', function () {
    $tournament = drawnTournament();

    $tournament->fixtures()->first()->update(['score' => '11-5 11-5 11-5 11-5']);

    expect($tournament->state())->toBe(TournamentState::Running);
});

test('a tournament with every fixture scored is finished', function () {
    $tournament = drawnTournament();

    $tournament->fixtures()->each(fn ($fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    expect($tournament->state())->toBe(TournamentState::Finished);
});

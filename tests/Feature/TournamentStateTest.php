<?php

use App\Models\Team;
use App\Models\Tournament;
use App\TournamentState;
use Illuminate\Support\Carbon;

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

test('a tournament that is not finished has no finished-at time', function () {
    $tournament = drawnTournament();

    expect($tournament->finishedAt())->toBeNull();
});

test('finishedAt is the last fixture result that was saved', function () {
    $tournament = drawnTournament();
    $fixtures = $tournament->fixtures;

    Carbon::setTestNow('2026-01-01 10:00:00');
    $fixtures[0]->update(['score' => '11-5 11-5 11-5 11-5']);
    $fixtures[1]->update(['score' => '11-5 11-5 11-5 11-5']);

    Carbon::setTestNow('2026-01-01 12:00:00');
    $fixtures[2]->update(['score' => '11-5 11-5 11-5 11-5']);
    $fixtures[3]->update(['score' => '11-5 11-5 11-5 11-5']);

    expect($tournament->finishedAt())->toEqual(Carbon::parse('2026-01-01 12:00:00'));
});

test('results are not locked right after the tournament finished', function () {
    $tournament = drawnTournament();
    $tournament->fixtures()->each(fn ($fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    expect($tournament->resultsLocked())->toBeFalse();
});

test('results are still editable just under 24 hours after finishing', function () {
    $tournament = drawnTournament();

    Carbon::setTestNow('2026-01-01 10:00:00');
    $tournament->fixtures()->each(fn ($fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    Carbon::setTestNow('2026-01-02 09:59:59');
    expect($tournament->resultsLocked())->toBeFalse();
});

test('results are locked more than 24 hours after finishing', function () {
    $tournament = drawnTournament();

    Carbon::setTestNow('2026-01-01 10:00:00');
    $tournament->fixtures()->each(fn ($fixture) => $fixture->update(['score' => '11-5 11-5 11-5 11-5']));

    Carbon::setTestNow('2026-01-02 10:00:01');
    expect($tournament->resultsLocked())->toBeTrue();
});

<?php

use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

function drawTournamentWithTeams(int $teamCount = 4): Tournament
{
    $tournament = Tournament::factory()->create(['rounds' => 1]);

    for ($i = 0; $i < $teamCount; $i++) {
        $tournament->teams()->attach(Team::factory()->create());
    }

    $tournament->draw();

    return $tournament;
}

test('the table lists pdf embeds a qr code that is specific to the tournament', function () {
    $tournamentA = drawTournamentWithTeams();
    $tournamentB = drawTournamentWithTeams();

    $pdfA = $tournamentA->tableLists(1)->Output('S');
    $pdfB = $tournamentB->tableLists(1)->Output('S');

    expect($pdfA)->toContain('/Subtype /Image');
    expect($pdfA)->not->toBe($pdfB);
});

test('the table lists pdf omits the qr code for a private tournament', function () {
    $tournament = drawTournamentWithTeams();
    $tournament->update(['private' => true]);

    $pdf = $tournament->tableLists(1)->Output('S');

    expect($pdf)->not->toContain('/Subtype /Image');
});

test('users can download the table lists pdf for a tournament they can view', function () {
    $user = User::factory()->create();
    $tournament = drawTournamentWithTeams();
    $tournament->update(['start' => now()->subDay()]);

    $response = $this->actingAs($user)->get(route('tournaments.lists', [$tournament, 1]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('users cannot download the table lists pdf for a tournament they cannot view', function () {
    $user = User::factory()->create();
    $tournament = drawTournamentWithTeams();
    $tournament->update(['start' => now()->addDay()]);

    $this->actingAs($user)->get(route('tournaments.lists', [$tournament, 1]))->assertForbidden();
});

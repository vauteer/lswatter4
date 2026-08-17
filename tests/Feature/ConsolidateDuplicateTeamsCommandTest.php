<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;

test('the command merges every pair of teams sharing the same two players', function () {
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    $keeper = Team::factory()->create(['player1_id' => $playerA->id, 'player2_id' => $playerB->id]);
    $duplicate = Team::factory()->create(['player1_id' => $playerB->id, 'player2_id' => $playerA->id]);

    $this->artisan('app:consolidate-duplicate-teams')
        ->expectsOutputToContain('Merged 1 duplicate team row(s).')
        ->assertSuccessful();

    expect(Team::find($keeper->id))->not->toBeNull();
    expect(Team::find($duplicate->id))->toBeNull();
});

test('the command reports pairs it could not merge because both play the same tournament', function () {
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    $teamOne = Team::factory()->create(['player1_id' => $playerA->id, 'player2_id' => $playerB->id]);
    $teamTwo = Team::factory()->create(['player1_id' => $playerB->id, 'player2_id' => $playerA->id]);

    $tournament = Tournament::factory()->create();
    $tournament->teams()->attach([$teamOne->id, $teamTwo->id]);

    $this->artisan('app:consolidate-duplicate-teams')
        ->expectsOutputToContain('Merged 0 duplicate team row(s).')
        ->expectsOutputToContain('1 pair(s) could not be merged automatically')
        ->assertSuccessful();

    expect(Team::find($teamOne->id))->not->toBeNull();
    expect(Team::find($teamTwo->id))->not->toBeNull();
});

<?php

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Validation\ValidationException;

test('merging a team reassigns its tournament registration and fixtures, then deletes it', function () {
    $keeper = Team::factory()->create();
    $duplicate = Team::factory()->create();

    $tournament = Tournament::factory()->create();
    $tournament->teams()->attach($duplicate);

    $fixture = Fixture::factory()->create([
        'tournament_id' => $tournament->id,
        'team1_id' => $duplicate->id,
    ]);

    $keeper->mergeWith($duplicate);

    expect(Team::find($duplicate->id))->toBeNull();
    expect($keeper->tournaments()->pluck('tournaments.id')->all())->toBe([$tournament->id]);
    expect($fixture->fresh()->team1_id)->toBe($keeper->id);
});

test('merging two teams already registered for the same tournament fails', function () {
    $keeper = Team::factory()->create();
    $duplicate = Team::factory()->create();

    $tournament = Tournament::factory()->create();
    $tournament->teams()->attach([$keeper->id, $duplicate->id]);

    expect(fn () => $keeper->mergeWith($duplicate))
        ->toThrow(ValidationException::class);

    expect(Team::find($duplicate->id))->not->toBeNull();
});

test('consolidating a player teams merges duplicate teams with the same partner', function () {
    $player = Player::factory()->create();
    $partner = Player::factory()->create();

    $keeper = Team::factory()->create(['player1_id' => $player->id, 'player2_id' => $partner->id]);
    $duplicate = Team::factory()->create(['player1_id' => $partner->id, 'player2_id' => $player->id]);

    $tournament = Tournament::factory()->create();
    $tournament->teams()->attach($duplicate);

    Team::consolidateDuplicatesForPlayer($player->id);

    expect(Team::find($duplicate->id))->toBeNull();
    expect($keeper->fresh()->tournaments()->pluck('tournaments.id')->all())->toBe([$tournament->id]);
});

test('consolidating all duplicates merges every pair of teams sharing the same two players', function () {
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();
    $playerC = Player::factory()->create();
    $playerD = Player::factory()->create();

    $keeper = Team::factory()->create(['player1_id' => $playerA->id, 'player2_id' => $playerB->id]);
    $duplicate = Team::factory()->create(['player1_id' => $playerB->id, 'player2_id' => $playerA->id]);
    $unrelated = Team::factory()->create(['player1_id' => $playerC->id, 'player2_id' => $playerD->id]);

    $merged = Team::consolidateAllDuplicates();

    expect($merged)->toBe(1);
    expect(Team::find($keeper->id))->not->toBeNull();
    expect(Team::find($duplicate->id))->toBeNull();
    expect(Team::find($unrelated->id))->not->toBeNull();
});

test('consolidating a player teams leaves duplicates registered for the same tournament unmerged', function () {
    $player = Player::factory()->create();
    $partner = Player::factory()->create();

    $keeper = Team::factory()->create(['player1_id' => $player->id, 'player2_id' => $partner->id]);
    $duplicate = Team::factory()->create(['player1_id' => $partner->id, 'player2_id' => $player->id]);

    $tournament = Tournament::factory()->create();
    $tournament->teams()->attach([$keeper->id, $duplicate->id]);

    Team::consolidateDuplicatesForPlayer($player->id);

    expect(Team::find($keeper->id))->not->toBeNull();
    expect(Team::find($duplicate->id))->not->toBeNull();
});

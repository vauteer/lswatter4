<?php

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('non-admins cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertForbidden();
});

test('admins can visit the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard reports possible duplicate players, user stats, and backup status', function () {
    $admin = User::factory()->admin()->create();
    Player::factory()->create(['name' => 'Anna Müller']);
    Player::factory()->create(['name' => 'Anna Mueller']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('duplicatePlayerCount', 1)
        ->where('users.total', 1)
        ->where('lastBackup', null));
});

test('the dashboard reports all-time team and player rankings', function () {
    $admin = User::factory()->admin()->create();
    $winners = Team::factory()->create();
    $losers = Team::factory()->create();

    Fixture::factory()->create([
        'team1_id' => $winners->id,
        'team2_id' => $losers->id,
        'score' => '11-5 11-5',
        'team1_won' => 2,
        'team2_won' => 0,
        'team1_points' => 22,
        'team2_points' => 10,
    ]);

    // Undrawn/unscored fixtures shouldn't count toward the ranking.
    Fixture::factory()->create(['score' => null]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('teamRanking', 2)
        ->where('teamRanking.0.id', $winners->id)
        ->where('teamRanking.0.won', 2)
        ->where('teamRanking.0.lost', 0)
        ->where('teamRanking.0.player1', $winners->player1->name)
        ->has('playerRanking', 4)
        ->where('playerRanking.0.won', 2)
        ->where('playerRanking.1.won', 2));
});

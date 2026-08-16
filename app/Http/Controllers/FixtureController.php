<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixtureUpdateRequest;
use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FixtureController extends Controller
{
    /**
     * Show the form for editing the result of the given fixture.
     */
    public function edit(Fixture $fixture): Response
    {
        Gate::authorize('update', $fixture);

        $tournament = $fixture->tournament;

        return Inertia::render('fixtures/Edit', [
            'fixture' => [
                'id' => $fixture->id,
                'tournamentId' => $fixture->tournament_id,
                'round' => $fixture->round,
                'score' => $fixture->score,
                'team1' => (string) $fixture->team1,
                'team2' => (string) $fixture->team2,
            ],
            'placeholder' => $this->placeholder($tournament),
            'gamesNeeded' => $tournament->games,
        ]);
    }

    /**
     * Update the result of the given fixture.
     */
    public function update(FixtureUpdateRequest $request, Fixture $fixture): RedirectResponse
    {
        Gate::authorize('update', $fixture);

        $fixture->calculate($request->validated('score'), true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Result saved.')]);

        return to_route('tournaments.show', ['tournament' => $fixture->tournament_id, 'round' => $fixture->round]);
    }

    private function placeholder(Tournament $tournament): string
    {
        $winPoints = $tournament->winpoints;
        $games = [];

        for ($i = 0; $i < $tournament->games; $i++) {
            $points1 = $i % 2 ? $winPoints : random_int(2, $winPoints - 1);
            $points2 = $i % 2 ? random_int(2, $winPoints - 1) : $winPoints;

            $games[] = "{$points1}-{$points2}";
        }

        return __('e.g.: :example', ['example' => implode(' ', $games)]);
    }
}

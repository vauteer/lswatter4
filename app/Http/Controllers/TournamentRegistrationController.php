<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentJoinRequest;
use App\Http\Requests\TournamentRegisterRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TournamentRegistrationController extends Controller
{
    /**
     * Display the registration form and currently registered participants.
     */
    public function index(Tournament $tournament): Response
    {
        Gate::authorize('update', $tournament);

        return Inertia::render('tournaments/Register', [
            'tournament' => new TournamentResource($tournament),
            'singlePlayers' => $tournament->singlePlayers()
                ->orderBy('name')
                ->get(['players.id', 'players.name']),
            'teams' => $tournament->teams()
                ->with(['player1', 'player2'])
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'player1' => $team->player1->name,
                    'player2' => $team->player2->name,
                ]),
            'allPlayers' => Player::orderBy('name')->get(['id', 'name']),
            'canDraw' => $tournament->canDraw(),
            'drawn' => $tournament->drawn(),
        ]);
    }

    /**
     * Register one player, or two players as a team, for the tournament.
     */
    public function store(TournamentRegisterRequest $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);
        abort_unless($tournament->registrationOpen(), 422);

        $data = $request->validated();

        $player1 = $this->resolvePlayer($data['player1_id'] ?? null, $data['new_player1_name'] ?? null);

        if ($player1->isRegisteredFor($tournament)) {
            throw ValidationException::withMessages([
                'player1_id' => __(':name is already registered for this tournament.', ['name' => $player1->name]),
            ]);
        }

        $hasSecondPlayer = ($data['player2_id'] ?? null) !== null || ($data['new_player2_name'] ?? null) !== null;

        if (! $hasSecondPlayer) {
            $tournament->singlePlayers()->attach($player1);

            $message = $this->discardDrawIfNeeded($tournament, __(':name registered.', ['name' => $player1->name]));
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('tournaments.register', $tournament);
        }

        $player2 = $this->resolvePlayer($data['player2_id'] ?? null, $data['new_player2_name'] ?? null);

        if ($player2->is($player1)) {
            throw ValidationException::withMessages([
                'player2_id' => __('Player 2 must be a different player than Player 1.'),
            ]);
        }

        if ($player2->isRegisteredFor($tournament)) {
            throw ValidationException::withMessages([
                'player2_id' => __(':name is already registered for this tournament.', ['name' => $player2->name]),
            ]);
        }

        $team = Team::findOrCreateForPlayers($player1, $player2);
        $tournament->teams()->attach($team);

        $message = $this->discardDrawIfNeeded($tournament, __('Team :team registered.', ['team' => (string) $team]));
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tournaments.register', $tournament);
    }

    /**
     * Join two already individually-registered players into a team.
     */
    public function join(TournamentJoinRequest $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);
        abort_unless($tournament->registrationOpen(), 422);

        [$player1Id, $player2Id] = $request->validated('player_ids');

        $registeredPlayerIds = $tournament->singlePlayers()
            ->whereIn('players.id', [$player1Id, $player2Id])
            ->pluck('players.id');

        if ($registeredPlayerIds->count() !== 2) {
            throw ValidationException::withMessages([
                'player_ids' => __('Both players must already be registered individually for this tournament.'),
            ]);
        }

        $player1 = Player::findOrFail((int) $player1Id);
        $player2 = Player::findOrFail((int) $player2Id);

        $team = Team::findOrCreateForPlayers($player1, $player2);

        $tournament->singlePlayers()->detach([$player1->id, $player2->id]);
        $tournament->teams()->syncWithoutDetaching($team);

        $message = $this->discardDrawIfNeeded($tournament, __(':player1 and :player2 joined into a team.', [
            'player1' => $player1->name,
            'player2' => $player2->name,
        ]));
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tournaments.register', $tournament);
    }

    /**
     * Unregister a single player from the tournament.
     */
    public function destroyPlayer(Tournament $tournament, Player $player): RedirectResponse
    {
        Gate::authorize('update', $tournament);
        abort_unless($tournament->registrationOpen(), 422);

        $tournament->singlePlayers()->detach($player);

        $message = __(':name unregistered.', ['name' => $player->name]);

        // Was only registered here, never played anywhere or paired into a
        // team - nothing links to this player record any more, so drop the
        // now-pointless entry instead of leaving it to accumulate.
        if (! $player->isUsed()) {
            $player->delete();
            $message = __(':name unregistered and removed, since they were never registered elsewhere.', ['name' => $player->name]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tournaments.register', $tournament);
    }

    /**
     * Unregister a team from the tournament.
     */
    public function destroyTeam(Tournament $tournament, Team $team): RedirectResponse
    {
        Gate::authorize('update', $tournament);
        abort_unless($tournament->registrationOpen(), 422);

        $teamLabel = (string) $team;
        $players = [$team->player1, $team->player2];

        $tournament->teams()->detach($team);

        $message = __('Team :team unregistered.', ['team' => $teamLabel]);

        // The pairing itself was never played and isn't registered for any
        // other tournament any more - nothing links to this team row any
        // more, so drop it, and with it, any player who was only ever part
        // of this team and never registered anywhere else either.
        if (Team::unused()->whereKey($team->id)->exists()) {
            $team->delete();

            $removedNames = collect($players)
                ->reject(fn (Player $player) => $player->isUsed())
                ->each(fn (Player $player) => $player->delete())
                ->pluck('name');

            if ($removedNames->isNotEmpty()) {
                $message .= ' '.__(':names removed, since they were never registered elsewhere.', [
                    'names' => $removedNames->implode(', '),
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tournaments.register', $tournament);
    }

    /**
     * If the tournament has already been drawn, the newly changed
     * roster no longer matches those fixtures, so discard them and
     * note it on the success message rather than asking first - the
     * roster change itself was the deliberate action here.
     */
    private function discardDrawIfNeeded(Tournament $tournament, string $message): string
    {
        if (! $tournament->drawn()) {
            return $message;
        }

        $tournament->discardDraw();

        return $message.' '.__('The existing draw was discarded because the roster changed.');
    }

    private function resolvePlayer(?int $id, ?string $newName): Player
    {
        if ($id !== null) {
            return Player::findOrFail($id);
        }

        /** @var string $newName */
        return Player::firstOrCreate(['name' => trim($newName)]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerStoreRequest;
use App\Http\Requests\PlayerUpdateRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    /**
     * Display a listing of the players.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Player::class);

        $search = $request->string('search')->trim()->toString();

        $players = Player::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('players/Index', [
            'players' => $players->through(fn (Player $player) => new PlayerResource($player)),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new player.
     */
    public function create(): Response
    {
        Gate::authorize('create', Player::class);

        return Inertia::render('players/Create');
    }

    /**
     * Store a newly created player.
     */
    public function store(PlayerStoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Player::class);

        Player::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player created.')]);

        return to_route('players.index');
    }

    /**
     * Show the form for editing the given player.
     */
    public function edit(Player $player): Response
    {
        Gate::authorize('update', $player);

        return Inertia::render('players/Edit', [
            'player' => new PlayerResource($player),
        ]);
    }

    /**
     * Update the given player.
     */
    public function update(PlayerUpdateRequest $request, Player $player): RedirectResponse
    {
        Gate::authorize('update', $player);

        $player->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player updated.')]);

        return to_route('players.index');
    }

    /**
     * Remove the given player.
     */
    public function destroy(Player $player): RedirectResponse
    {
        Gate::authorize('delete', $player);

        $player->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player deleted.')]);

        return to_route('players.index');
    }
}

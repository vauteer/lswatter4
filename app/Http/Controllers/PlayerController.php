<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerMergeRequest;
use App\Http\Requests\PlayerStoreRequest;
use App\Http\Requests\PlayerUpdateRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    private const int PER_PAGE = 15;

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
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('players/Index', [
            'players' => $players->through(fn (Player $player) => new PlayerResource($player)),
            'filters' => ['search' => $search],
            'duplicateGroups' => Player::possibleDuplicateGroups()
                ->map(fn ($group) => $group->map(fn (Player $player) => [
                    'id' => $player->id,
                    'name' => $player->name,
                ])->values()),
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

        $player = Player::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player created.')]);

        return to_route('players.index', ['page' => $this->pageOf($player)]);
    }

    /**
     * Show the form for editing the given player.
     */
    public function edit(Request $request, Player $player): Response
    {
        Gate::authorize('update', $player);

        return Inertia::render('players/Edit', [
            'player' => new PlayerResource($player),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
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

        return to_route('players.index', ['page' => $this->pageOf($player)]);
    }

    /**
     * Merge one or more duplicate players into the selected one.
     */
    public function mergeDuplicates(PlayerMergeRequest $request): RedirectResponse
    {
        Gate::authorize('merge', Player::class);

        $keepId = (int) $request->validated('keep_id');
        $duplicateIds = array_values(array_diff(
            array_map('intval', $request->validated('player_ids')),
            [$keepId],
        ));

        $keeper = Player::findOrFail($keepId);
        $duplicates = Player::whereIn('id', $duplicateIds)->get();

        DB::transaction(function () use ($keeper, $duplicates) {
            foreach ($duplicates as $duplicate) {
                $keeper->mergeWith($duplicate);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count players merged into :name.', [
            'count' => (string) $duplicates->count(),
            'name' => $keeper->name,
        ])]);

        return to_route('players.index');
    }

    /**
     * Remove the given player.
     */
    public function destroy(Player $player): RedirectResponse
    {
        Gate::authorize('delete', $player);

        $page = $this->pageOf($player);

        $player->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player deleted.')]);

        return to_route('players.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * The index page on which the given player appears.
     */
    private function pageOf(Player $player): int
    {
        $position = Player::where('name', '<=', $player->name)->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the players index.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Player::count() / self::PER_PAGE));
    }
}

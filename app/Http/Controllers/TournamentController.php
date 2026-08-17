<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentStoreRequest;
use App\Http\Requests\TournamentUpdateRequest;
use App\Http\Resources\FixtureResource;
use App\Http\Resources\TournamentResource;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * Display a listing of the tournaments.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tournament::class);

        $search = $request->string('search')->trim()->toString();
        $playerId = $request->integer('player_id') ?: null;

        $tournaments = Tournament::query()
            ->visibleTo($request->user())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($playerId !== null, fn ($query) => $query->playedBy($playerId))
            ->orderByDesc('start')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('tournaments/Index', [
            'tournaments' => $tournaments->through(fn (Tournament $tournament) => new TournamentResource($tournament)),
            'filters' => ['search' => $search, 'player_id' => $playerId],
            'players' => Player::orderBy('name')->get(['id', 'name']),
            'teamRanking' => Team::allTimeStandings(),
            'playerRanking' => Player::allTimeStandings(),
        ]);
    }

    /**
     * Display the given tournament's fixtures and standings.
     */
    public function show(Request $request, Tournament $tournament): Response
    {
        Gate::authorize('view', $tournament);

        $round = $request->integer('round') ?: $tournament->firstIncompleteRound();

        return Inertia::render('tournaments/Show', [
            'tournament' => new TournamentResource($tournament),
            'currentRound' => $round,
            'standings' => $tournament->standings(),
            'fixtures' => FixtureResource::collection($tournament->fixtures()
                ->where('round', $round)
                ->orderBy('table_number')
                ->get()),
            'canDraw' => $tournament->canDraw(),
            'drawn' => $tournament->drawn(),
        ]);
    }

    /**
     * Randomly draw (or redraw) the tournament's fixtures from its
     * currently registered teams.
     */
    public function draw(Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);

        abort_unless($tournament->canDraw(), 422);

        $redraw = $tournament->drawn();

        $tournament->draw();

        Inertia::flash('toast', ['type' => 'success', 'message' => $redraw
            ? __('Tournament redrawn.')
            : __('Tournament drawn.')]);

        return to_route('tournaments.show', $tournament);
    }

    /**
     * Download the table lists for the given tournament round as a PDF.
     */
    public function tableLists(Tournament $tournament, int $round): HttpResponse
    {
        Gate::authorize('view', $tournament);

        return new HttpResponse($tournament->tableLists($round)->Output(), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create(): Response
    {
        Gate::authorize('create', Tournament::class);

        return Inertia::render('tournaments/Create');
    }

    /**
     * Store a newly created tournament.
     */
    public function store(TournamentStoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Tournament::class);

        $tournament = Tournament::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament created.')]);

        return to_route('tournaments.index', ['page' => $this->pageOf($tournament, $request->user())]);
    }

    /**
     * Show the form for editing the given tournament.
     */
    public function edit(Request $request, Tournament $tournament): Response
    {
        Gate::authorize('update', $tournament);

        return Inertia::render('tournaments/Edit', [
            'tournament' => new TournamentResource($tournament),
            'started' => $tournament->started(),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    /**
     * Update the given tournament.
     */
    public function update(TournamentUpdateRequest $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);

        $data = $request->validated();

        $formatChanged = (int) $data['rounds'] !== $tournament->rounds
            || (int) $data['games'] !== $tournament->games
            || (int) $data['winpoints'] !== $tournament->winpoints;
        $discardDraw = $tournament->drawn() && $formatChanged;

        $tournament->update($data);

        $message = __('Tournament updated.');

        if ($discardDraw) {
            $tournament->discardDraw();
            $message .= ' '.__('The existing draw was discarded because the tournament format changed.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tournaments.index', ['page' => $this->pageOf($tournament, $request->user())]);
    }

    /**
     * Remove the given tournament.
     */
    public function destroy(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('delete', $tournament);

        $page = $this->pageOf($tournament, $request->user());

        $tournament->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament deleted.')]);

        return to_route('tournaments.index', ['page' => min($page, $this->lastPage($request->user()))]);
    }

    /**
     * The index page on which the given tournament appears.
     */
    private function pageOf(Tournament $tournament, ?User $user): int
    {
        $position = Tournament::query()
            ->visibleTo($user)
            ->where(fn ($query) => $query
                ->where('start', '>', $tournament->start)
                ->orWhere(fn ($query) => $query
                    ->where('start', $tournament->start)
                    ->where('id', '<=', $tournament->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the tournaments index.
     */
    private function lastPage(?User $user): int
    {
        return max(1, (int) ceil(Tournament::query()->visibleTo($user)->count() / self::PER_PAGE));
    }
}

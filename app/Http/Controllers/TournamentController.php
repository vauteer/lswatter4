<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentStoreRequest;
use App\Http\Requests\TournamentUpdateRequest;
use App\Http\Resources\FixtureResource;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    /**
     * Display a listing of the tournaments.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tournament::class);

        $search = $request->string('search')->trim()->toString();

        $tournaments = Tournament::query()
            ->visibleTo($request->user())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('start')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tournaments/Index', [
            'tournaments' => $tournaments->through(fn (Tournament $tournament) => new TournamentResource($tournament)),
            'filters' => ['search' => $search],
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

        Tournament::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament created.')]);

        return to_route('tournaments.index');
    }

    /**
     * Show the form for editing the given tournament.
     */
    public function edit(Tournament $tournament): Response
    {
        Gate::authorize('update', $tournament);

        return Inertia::render('tournaments/Edit', [
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Update the given tournament.
     */
    public function update(TournamentUpdateRequest $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);

        $tournament->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament updated.')]);

        return to_route('tournaments.index');
    }

    /**
     * Remove the given tournament.
     */
    public function destroy(Tournament $tournament): RedirectResponse
    {
        Gate::authorize('delete', $tournament);

        $tournament->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament deleted.')]);

        return to_route('tournaments.index');
    }
}

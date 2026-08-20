<?php

namespace App\Models;

use App\Concerns\RanksStandings;
use App\TournamentState;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Fpdf\Fpdf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property CarbonInterface $start
 * @property int $rounds
 * @property int $games
 * @property int $winpoints
 * @property bool $private
 * @property int $created_by
 * @property-read User $creator
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'start', 'rounds', 'games', 'winpoints', 'private', 'created_by'])]
class Tournament extends Model
{
    /** @use HasFactory<Factory<Tournament>> */
    use HasFactory;

    use RanksStandings;

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'private' => 'boolean',
        ];
    }

    /**
     * Players registered individually, not yet joined into a team.
     *
     * @return BelongsToMany<Player, $this>
     */
    public function singlePlayers(): BelongsToMany
    {
        return $this->belongsToMany(Player::class)
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withTimestamps();
    }

    /**
     * All players participating as part of a team registered for this
     * tournament, as opposed to singlePlayers() who are registered
     * individually.
     *
     * @return EloquentCollection<int, Player>
     */
    public function teamPlayers(): EloquentCollection
    {
        $playerIds = $this->teams()
            ->get(['player1_id', 'player2_id'])
            ->flatMap(fn (Team $team): array => [$team->player1_id, $team->player2_id]);

        return Player::whereIn('id', $playerIds)->get();
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function currentPlayerCount(): int
    {
        return $this->teams()->count() * 2 + $this->singlePlayers()->count();
    }

    public function drawn(): bool
    {
        return $this->fixtures()->count() > 0;
    }

    /**
     * Discard the current draw. Used when the roster changes after
     * drawing but before any result is entered, since the existing
     * fixtures no longer reflect who's actually registered.
     */
    public function discardDraw(): void
    {
        $this->fixtures()->delete();
    }

    /**
     * Whether the tournament currently has a valid, complete team roster
     * to draw fixtures from: enough teams to fill at least two tables,
     * an even number of them so every table has two teams, no single
     * players left waiting to be joined into a team, and no result has
     * been entered yet that a (re)draw would otherwise invalidate.
     */
    public function canDraw(): bool
    {
        if ($this->started()) {
            return false;
        }

        $teamsCount = $this->teams()->count();

        return $teamsCount >= 4
            && $teamsCount % 2 === 0
            && $this->singlePlayers()->count() === 0;
    }

    public function started(): bool
    {
        return $this->drawn() && $this->fixtures()->whereNotNull('score')->count() > 0;
    }

    /**
     * Whether the roster (players/teams) can still be changed. Once a
     * result has been entered, registering or unregistering a
     * participant would silently desync the standings from what was
     * actually played, so registration locks at that point.
     */
    public function registrationOpen(): bool
    {
        return ! $this->started();
    }

    public function finished(): bool
    {
        Fixture::where('tournament_id', $this->id)
            ->where('score', '')
            ->update(['score' => null]);

        return $this->drawn() && $this->fixtures()->whereNull('score')->count() === 0;
    }

    /**
     * When the tournament became fully scored - the last fixture result
     * that was saved - or null while it isn't finished yet. Derived
     * rather than stored, so correcting a result within the edit window
     * pushes this (and the window) later, same as the original save did.
     */
    public function finishedAt(): ?CarbonInterface
    {
        if (! $this->finished()) {
            return null;
        }

        return $this->fixtures()->latest('updated_at')->first()?->updated_at;
    }

    /**
     * Once a finished tournament is more than a day past finishing,
     * its results can no longer be edited.
     */
    public function resultsLocked(): bool
    {
        return $this->finishedAt()?->addDay()->isPast() ?? false;
    }

    /**
     * Where the tournament currently stands in its lifecycle: still
     * building its roster, drawn but not yet started, in progress, or
     * fully scored.
     */
    public function state(): TournamentState
    {
        if (! $this->drawn()) {
            return TournamentState::Registering;
        }

        if ($this->finished()) {
            return TournamentState::Finished;
        }

        if ($this->started()) {
            return TournamentState::Running;
        }

        return TournamentState::Drawn;
    }

    /**
     * The round to preselect when opening the tournament: the earliest
     * round that still has a fixture without a result, i.e. the round
     * play is currently at. Falls back to the first round if nothing
     * has been drawn yet, or the last round once everything is scored.
     */
    public function firstIncompleteRound(): int
    {
        if (! $this->drawn()) {
            return 1;
        }

        Fixture::where('tournament_id', $this->id)
            ->where('score', '')
            ->update(['score' => null]);

        return $this->fixtures()->whereNull('score')->min('round') ?? $this->rounds;
    }

    /**
     * @return Collection<int, array{name: string}>
     */
    public function singlePlayersAsArray(): Collection
    {
        return $this->singlePlayers()
            ->get(['player_id', 'name'])
            ->mapWithKeys(function ($player) {
                /** @var Player&object{player_id: int} $player */
                return [$player->player_id => [
                    'name' => $player->name,
                ]];
            });
    }

    /**
     * @return Collection<int, array{id: int, player1: string, player2: string}>
     */
    public function teamsAsArray(): Collection
    {
        return $this->teams()
            ->get(['team_id', 'player1_id', 'player2_id'])
            ->map(function ($team) {
                /** @var Team&object{team_id: int} $team */
                return [
                    'id' => $team->team_id,
                    'player1' => $team->player1->name,
                    'player2' => $team->player2->name,
                ];
            });
    }

    public function draw(): void
    {
        if (! $this->canDraw()) {
            return;
        }

        $teamsCount = $this->teams()->count();
        if ($teamsCount < 4) {
            return;
        }

        $tableCount = intdiv($teamsCount, 2);
        if ($tableCount < 1) {
            return;
        }

        $this->fixtures()->delete();

        $randomTeams = $this->teams()->inRandomOrder()->pluck('teams.id')->toArray();
        [$home, $away] = array_chunk($randomTeams, $tableCount);

        for ($round = 0; $round < $this->rounds; $round++) {
            for ($i = 0; $i < $tableCount; $i++) {
                $this->fixtures()->create([
                    'team1_id' => $home[$i],
                    'team2_id' => $away[$i],
                    'round' => $round + 1,
                    'table_number' => $i + 1,
                ]);
            }

            array_unshift($away, array_pop($away)); // rotate right/clockwise
            // $away[] = array_shift($away); //rotate left/counterclockwise
        }
    }

    /**
     * @return array<int, array{id: int, player1: string, player2: string, won: int, lost: int, pointsWon: int, pointsLost: int}>
     */
    public function standings(?int $tillRound = null): array
    {
        if ($tillRound === null) {
            $tillRound = $this->rounds;
        }

        $standings = [];
        $teams = $this->teams;
        if (count($teams) === 0) {
            return $standings;
        }

        foreach ($teams as $team) {
            $standings[$team->id] = [
                'id' => $team->id,
                'player1' => $team->player1->name,
                'player2' => $team->player2->name,
                'won' => 0,
                'lost' => 0,
                'pointsWon' => 0,
                'pointsLost' => 0,
            ];
        }

        /** @var array<int, array{id: int, player1: string, player2: string, won: int, lost: int, pointsWon: int, pointsLost: int}> $standings */
        $fixtures = $this->fixtures()->where('round', '<=', $tillRound)->get();

        foreach ($fixtures as $fixture) {
            $home = $standings[$fixture->team1_id];
            $home['won'] += $fixture->team1_won;
            $home['lost'] += $fixture->team2_won;
            $home['pointsWon'] += $fixture->team1_points;
            $home['pointsLost'] += $fixture->team2_points;
            $standings[$fixture->team1_id] = $home;

            $away = $standings[$fixture->team2_id];
            $away['won'] += $fixture->team2_won;
            $away['lost'] += $fixture->team1_won;
            $away['pointsWon'] += $fixture->team2_points;
            $away['pointsLost'] += $fixture->team1_points;
            $standings[$fixture->team2_id] = $away;
        }

        self::rankStandings($standings);

        return $standings;
    }

    public function tableLists(int $round): Fpdf
    {
        $gamesPerRound = $this->games;

        $fixtures = $this->fixtures()->where('round', $round)
            ->orderBy('table_number')
            ->get();

        $pdf = new Fpdf('L', 'mm', 'A4');
        $pdf->SetTitle(mb_convert_encoding("{$this->name} - Tischlisten Runde {$round}", 'ISO-8859-1', 'UTF-8'));

        $qrPath = null;

        if (! $this->private) {
            $qrCode = (new Writer(
                new ImageRenderer(
                    new RendererStyle(300, 0),
                    new ImagickImageBackEnd('png', 100, antialias: false)
                )
            ))->writeString(route('tournaments.show', $this));

            $qrPath = tempnam(sys_get_temp_dir(), 'qr');
            file_put_contents($qrPath, $qrCode);
        }

        foreach ($fixtures as $fixture) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(120, 10, 'Runde '.$fixture->round.' - Tisch '.$fixture->table_number, 0, 0);
            if ($qrPath !== null) {
                $pdf->Image($qrPath, 95, 5, 20, 20, 'png');
            }
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(60, 7, 'Schreiber:');
            $pdf->Line(40, 27, 120, 27);
            $pdf->Ln(10);
            $pdf->Cell(60, 7, mb_convert_encoding($fixture->team1->player1->name, 'ISO-8859-1', 'UTF-8'));
            $pdf->Cell(60, 7, mb_convert_encoding($fixture->team2->player1->name, 'ISO-8859-1', 'UTF-8'));
            $pdf->Ln(7);
            $pdf->Cell(60, 7, mb_convert_encoding($fixture->team1->player2->name, 'ISO-8859-1', 'UTF-8'));
            $pdf->Cell(60, 7, mb_convert_encoding($fixture->team2->player2->name, 'ISO-8859-1', 'UTF-8'));

            $pdf->Line(60, 30, 60, 50 + ($gamesPerRound * 20));
            $pdf->Line(0, 50, 120, 50);

            for ($i = 1; $i <= $gamesPerRound; $i++) {
                $y = 50 + ($i * 20);
                $pdf->Line(0, $y, 120, $y);
            }

            $pdf->Ln(15 + (20 * $gamesPerRound));
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(60, 6, mb_convert_encoding(config('app.name'), 'ISO-8859-1', 'UTF-8'));
            $pdf->Cell(60, 6, config('app.url'));
            $pdf->Ln(6);
            $pdf->Cell(120, 6, chr(169).' 2016 Gerald Lindner');
        }

        if ($qrPath !== null) {
            unlink($qrPath);
        }

        return $pdf;
    }

    public function getScoreRegex(): string
    {
        return '^('.Fixture::SCORE_REGEX.' ){'.$this->games - 1 .'}'.Fixture::SCORE_REGEX.'$';
    }

    public function modifiableBy(?User $user): bool
    {
        return $user !== null && ($user->admin || $user->id === $this->created_by);
    }

    public function canShow(?User $user): bool
    {
        if ($user === null) {
            return $this->start < now();
        } else {
            return $user->admin || ($this->start < now() || $this->created_by === $user->id);
        }
    }

    /**
     * @param  Builder<Tournament>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        if ($user?->admin) {
            return;
        }

        $query->where('start', '<', now())
            ->where('private', false);

        if ($user !== null) {
            $query->orWhere('created_by', $user->id);
        }
    }

    /**
     * @param  Builder<Tournament>  $query
     */
    #[Scope]
    protected function createdBy(Builder $query, int $userId): void
    {
        $query->where('created_by', $userId);
    }

    /**
     * @param  Builder<Tournament>  $query
     */
    #[Scope]
    protected function playedBy(Builder $query, int $playerId): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereHas('singlePlayers', fn (Builder $query) => $query->where('players.id', $playerId))
            ->orWhereHas('teams', fn (Builder $query) => $query
                ->where('teams.player1_id', $playerId)
                ->orWhere('teams.player2_id', $playerId)));
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Fpdf\Fpdf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'private' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
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
        return $this->teams()->count() * 2 + $this->players()->count();
    }

    public function drawn(): bool
    {
        return $this->fixtures()->count() > 0;
    }

    /**
     * Whether the tournament currently has a valid, complete team roster
     * to draw fixtures from: enough teams to fill at least two tables,
     * an even number of them so every table has two teams, and no
     * single players left waiting to be joined into a team.
     */
    public function canDraw(): bool
    {
        $teamsCount = $this->teams()->count();

        return $teamsCount >= 4
            && $teamsCount % 2 === 0
            && $this->players()->count() === 0;
    }

    public function started(): bool
    {
        return $this->drawn() && $this->fixtures()->whereNotNull('score')->count() > 0;
    }

    public function finished(): bool
    {
        Fixture::where('tournament_id', $this->id)
            ->where('score', '')
            ->update(['score' => null]);

        return $this->drawn() && $this->fixtures()->whereNull('score')->count() === 0;
    }

    /**
     * @return Collection<int, array{name: string}>
     */
    public function playersAsArray(): Collection
    {
        return $this->players()
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

        $games = $pointsDifference = $pointsWon = [];
        foreach ($standings as $id => $ranking) {
            $games[$id] = $ranking['won'];
            $pointsDifference[$id] = $ranking['pointsWon'] - $ranking['pointsLost'];
            $pointsWon[$id] = $ranking['pointsWon'];
        }

        array_multisort($games, SORT_DESC, $pointsDifference, SORT_DESC, $pointsWon, SORT_DESC, $standings);

        return $standings;
    }

    public function tableLists(int $round): Fpdf
    {
        $gamesPerRound = $this->games;

        $fixtures = $this->fixtures()->where('round', $round)
            ->orderBy('table_number')
            ->get();

        $pdf = new Fpdf('L', 'mm', 'A4');

        foreach ($fixtures as $fixture) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(120, 10, 'Runde '.$fixture->round.' - Tisch '.$fixture->table_number, 0, 0);
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
            $pdf->Cell(60, 6, 'LS-Watter 3');
            $pdf->Cell(60, 6, 'http://watter.it-ruler.de');
            $pdf->Ln(6);
            $pdf->Cell(120, 6, chr(169).' 2016 Gerald Lindner');
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
        $query->whereIn('id', DB::table('tournaments')
            ->join('team_tournament', 'tournaments.id', '=',
                'team_tournament.tournament_id')
            ->join('teams', 'teams.id', '=', 'team_tournament.team_id')
            ->where('teams.player1_id', $playerId)
            ->orWhere('teams.player2_id', $playerId)
            ->pluck('tournaments.id'));
    }
}

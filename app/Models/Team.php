<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $player1_id
 * @property int $player2_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['player1_id', 'player2_id'])]
#[Hidden(['pivot'])]
class Team extends Model
{
    /** @use HasFactory<Factory<Team>> */
    use HasFactory;

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsToMany<Tournament, $this>
     */
    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class)
            ->withTimestamps();
    }

    public function __toString(): string
    {
        return $this->player1->name.'/'.$this->player2->name;
    }

    /**
     * Find the team pairing these two players, regardless of which one is
     * player1/player2, or create it if it doesn't exist yet.
     */
    public static function findOrCreateForPlayers(Player $player1, Player $player2): self
    {
        $team = static::query()
            ->where(fn (Builder $query) => $query
                ->where('player1_id', $player1->id)
                ->where('player2_id', $player2->id))
            ->orWhere(fn (Builder $query) => $query
                ->where('player1_id', $player2->id)
                ->where('player2_id', $player1->id))
            ->first();

        return $team ?? static::create([
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
        ]);
    }

    /**
     * All-time team standings across every scored fixture ever played
     * (regardless of tournament), ranked the same way as a single
     * tournament's standings(): games won, then point difference, then
     * points won.
     *
     * @return array<int, array{id: int, player1: string, player2: string, played: int, won: int, lost: int, pointsWon: int, pointsLost: int}>
     */
    public static function allTimeStandings(int $limit = 10): array
    {
        $asTeam1 = DB::table('fixtures')
            ->whereNotNull('score')
            ->select([
                'team1_id as team_id',
                'team1_won as won',
                'team2_won as lost',
                'team1_points as points_won',
                'team2_points as points_lost',
            ]);

        $asTeam2 = DB::table('fixtures')
            ->whereNotNull('score')
            ->select([
                'team2_id as team_id',
                'team2_won as won',
                'team1_won as lost',
                'team2_points as points_won',
                'team1_points as points_lost',
            ]);

        $aggregated = DB::query()
            ->fromSub($asTeam1->unionAll($asTeam2), 'results')
            ->selectRaw('team_id, count(*) as played, sum(won) as won, sum(lost) as lost, sum(points_won) as points_won, sum(points_lost) as points_lost')
            ->groupBy('team_id')
            ->get()
            ->keyBy('team_id');

        if ($aggregated->isEmpty()) {
            return [];
        }

        $standings = [];
        foreach ($aggregated as $teamId => $row) {
            $standings[$teamId] = [
                'id' => $teamId,
                'played' => (int) $row->played,
                'won' => (int) $row->won,
                'lost' => (int) $row->lost,
                'pointsWon' => (int) $row->points_won,
                'pointsLost' => (int) $row->points_lost,
            ];
        }

        $games = $pointsDifference = $pointsWon = [];
        foreach ($standings as $id => $ranking) {
            $games[$id] = $ranking['won'];
            $pointsDifference[$id] = $ranking['pointsWon'] - $ranking['pointsLost'];
            $pointsWon[$id] = $ranking['pointsWon'];
        }

        array_multisort($games, SORT_DESC, $pointsDifference, SORT_DESC, $pointsWon, SORT_DESC, $standings);

        $standings = array_slice($standings, 0, $limit);

        $teams = static::whereIn('id', array_column($standings, 'id'))
            ->with(['player1', 'player2'])
            ->get()
            ->keyBy('id');

        foreach ($standings as &$row) {
            $team = $teams[$row['id']];
            $row['player1'] = $team->player1->name;
            $row['player2'] = $team->player2->name;
        }

        return $standings;
    }

    /**
     * @param  Builder<Team>  $query
     */
    #[Scope]
    protected function unused(Builder $query): void
    {
        $query->whereNotIn('id',
            DB::table('fixtures')->select('team1_id as team_id')
                ->union(DB::table('fixtures')->select('team2_id as team_id'))
                ->union(DB::table('team_tournament')->select('team_id'))
                ->pluck('team_id'));
    }
}

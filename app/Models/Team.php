<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * Merge another team pairing the same two players into this one: its
     * tournament registrations and fixtures are reassigned here, then it's
     * deleted.
     *
     * @throws ValidationException if both teams are already registered
     *                             (individually of each other) for the
     *                             same tournament - merging them would
     *                             make the team appear twice there.
     */
    public function mergeWith(Team $duplicate): void
    {
        $shared = $this->tournaments()->pluck('tournaments.id')
            ->intersect($duplicate->tournaments()->pluck('tournaments.id'));

        if ($shared->isNotEmpty()) {
            throw ValidationException::withMessages([
                'team_id' => __(':team1 and :team2 are both already registered for :tournaments - resolve that first.', [
                    'team1' => (string) $this,
                    'team2' => (string) $duplicate,
                    'tournaments' => Tournament::whereIn('id', $shared)->pluck('name')->implode(', '),
                ]),
            ]);
        }

        DB::transaction(function () use ($duplicate) {
            DB::table('team_tournament')->where('team_id', $duplicate->id)->update(['team_id' => $this->id]);
            DB::table('fixtures')->where('team1_id', $duplicate->id)->update(['team1_id' => $this->id]);
            DB::table('fixtures')->where('team2_id', $duplicate->id)->update(['team2_id' => $this->id]);

            $duplicate->delete();
        });
    }

    /**
     * After merging two players into one, some of that player's teams may
     * now pair them with the same partner as another of their teams -
     * split across two rows only because the players hadn't been merged
     * yet. Consolidates any such teams for the given player, skipping
     * pairs that can't be merged because both already played the same
     * tournament (left for manual resolution).
     */
    public static function consolidateDuplicatesForPlayer(int $playerId): void
    {
        $teams = static::where('player1_id', $playerId)->orWhere('player2_id', $playerId)->get();

        $byPartner = $teams->groupBy(fn (self $team): int => $team->player1_id === $playerId ? $team->player2_id : $team->player1_id);

        self::mergeDuplicatesWithinGroups($byPartner);
    }

    /**
     * Consolidates every team pairing the same two players (regardless of
     * which is player1/player2) into a single row, across the whole
     * table - historical data can have several such rows for the same
     * pair from before team reuse was enforced on registration. Skips
     * pairs that can't be merged because more than one of them is already
     * registered for the same tournament (left for manual resolution).
     *
     * @return int how many duplicate rows were merged away
     */
    public static function consolidateAllDuplicates(): int
    {
        return self::mergeDuplicatesWithinGroups(self::duplicatePairs());
    }

    /**
     * Groups of teams that pair the same two players (regardless of which
     * is player1/player2). Only pairs with more than one row are
     * included - normally left over because they're both already
     * registered for the same tournament, so consolidateAllDuplicates()
     * can't merge them automatically.
     *
     * @return SupportCollection<int, EloquentCollection<int, self>>
     */
    public static function duplicatePairs(): SupportCollection
    {
        $teams = static::with(['player1', 'player2'])->get();

        return $teams
            ->groupBy(fn (self $team): string => implode('-', [
                min($team->player1_id, $team->player2_id),
                max($team->player1_id, $team->player2_id),
            ]))
            ->filter(fn (EloquentCollection $group): bool => $group->count() > 1)
            ->values();
    }

    /**
     * @param  SupportCollection<array-key, EloquentCollection<int, self>>  $groups
     */
    private static function mergeDuplicatesWithinGroups(SupportCollection $groups): int
    {
        $merged = 0;

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keeper = $group->shift();

            foreach ($group as $duplicate) {
                try {
                    $keeper->mergeWith($duplicate);
                    $merged++;
                } catch (ValidationException) {
                    // Both teams already played the same tournament - leave
                    // them separate; needs manual resolution.
                }
            }
        }

        return $merged;
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

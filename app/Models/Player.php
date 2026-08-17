<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
#[Hidden(['pivot'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Tournament, $this>
     */
    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class)
            ->withTimestamps();
    }

    /**
     * @return Builder<Tournament>
     */
    public function playedTournaments(): Builder
    {
        return Tournament::playedBy($this->id);
    }

    /**
     * @return Collection<int, Team>
     */
    public function teams(): Collection
    {
        return Team::where('player1_id', $this->id)->orWhere('player2_id', $this->id)->get();
    }

    public function isUsed(): bool
    {
        return $this->tournaments()->count() > 0 || $this->teams()->count() > 0;
    }

    public function isRegisteredFor(Tournament $tournament): bool
    {
        return $tournament->players()->where('players.id', $this->id)->exists()
            || $tournament->teams()
                ->where(fn (Builder $query) => $query
                    ->where('teams.player1_id', $this->id)
                    ->orWhere('teams.player2_id', $this->id))
                ->exists();
    }

    /**
     * The tournaments this player is present in, whether registered
     * individually or as part of a team.
     *
     * @return SupportCollection<int, int>
     */
    public function tournamentIds(): SupportCollection
    {
        $individual = $this->tournaments()->pluck('tournaments.id');

        $viaTeams = Tournament::query()
            ->whereHas('teams', fn (Builder $query) => $query
                ->where('teams.player1_id', $this->id)
                ->orWhere('teams.player2_id', $this->id))
            ->pluck('id');

        return $individual->merge($viaTeams)->unique()->values();
    }

    /**
     * Merge another player into this one: their tournament registrations
     * and team memberships are reassigned here, then they're deleted.
     * Used to clean up the same person entered twice under different
     * spelling.
     *
     * @throws ValidationException if both players are already present
     *                             (individually or via a team) in the
     *                             same tournament - merging them would
     *                             make this player appear twice there.
     */
    public function mergeWith(Player $duplicate): void
    {
        $shared = $this->tournamentIds()->intersect($duplicate->tournamentIds());

        if ($shared->isNotEmpty()) {
            throw ValidationException::withMessages([
                'player_ids' => __(':name1 and :name2 are both already registered for :tournaments - resolve that first.', [
                    'name1' => $this->name,
                    'name2' => $duplicate->name,
                    'tournaments' => Tournament::whereIn('id', $shared)->pluck('name')->implode(', '),
                ]),
            ]);
        }

        DB::transaction(function () use ($duplicate) {
            DB::table('player_tournament')->where('player_id', $duplicate->id)->update(['player_id' => $this->id]);
            DB::table('teams')->where('player1_id', $duplicate->id)->update(['player1_id' => $this->id]);
            DB::table('teams')->where('player2_id', $duplicate->id)->update(['player2_id' => $this->id]);

            $duplicate->delete();
        });
    }

    /**
     * All-time standings across every scored fixture ever played
     * (regardless of tournament or partner), ranked the same way as a
     * single tournament's standings(): games won, then point difference,
     * then points won. A fixture's result counts for both players of the
     * team that played it.
     *
     * @return array<int, array{id: int, name: string, played: int, won: int, lost: int, pointsWon: int, pointsLost: int}>
     */
    public static function allTimeStandings(int $limit = 10): array
    {
        $branch = fn (string $teamColumn, string $wonColumn, string $lostColumn, string $pointsWonColumn, string $pointsLostColumn, string $playerColumn) => DB::table('fixtures')
            ->join('teams', 'teams.id', '=', "fixtures.{$teamColumn}")
            ->whereNotNull('fixtures.score')
            ->select([
                "teams.{$playerColumn} as player_id",
                "fixtures.{$wonColumn} as won",
                "fixtures.{$lostColumn} as lost",
                "fixtures.{$pointsWonColumn} as points_won",
                "fixtures.{$pointsLostColumn} as points_lost",
            ]);

        $results = $branch('team1_id', 'team1_won', 'team2_won', 'team1_points', 'team2_points', 'player1_id')
            ->unionAll($branch('team1_id', 'team1_won', 'team2_won', 'team1_points', 'team2_points', 'player2_id'))
            ->unionAll($branch('team2_id', 'team2_won', 'team1_won', 'team2_points', 'team1_points', 'player1_id'))
            ->unionAll($branch('team2_id', 'team2_won', 'team1_won', 'team2_points', 'team1_points', 'player2_id'));

        $aggregated = DB::query()
            ->fromSub($results, 'results')
            ->selectRaw('player_id, count(*) as played, sum(won) as won, sum(lost) as lost, sum(points_won) as points_won, sum(points_lost) as points_lost')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        if ($aggregated->isEmpty()) {
            return [];
        }

        $standings = [];
        foreach ($aggregated as $playerId => $row) {
            $standings[$playerId] = [
                'id' => $playerId,
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

        $players = static::whereIn('id', array_column($standings, 'id'))->get()->keyBy('id');

        foreach ($standings as &$row) {
            $row['name'] = $players[$row['id']]->name;
        }

        return $standings;
    }

    /**
     * @param  Builder<Player>  $query
     */
    #[Scope]
    protected function unused(Builder $query): void
    {
        $query->whereNotIn('id',
            DB::table('teams')->select('player1_id as player_id')
                ->union(DB::table('teams')->select('player2_id as player_id'))
                ->union(DB::table('player_tournament')->select('player_id'))
                ->pluck('player_id'));
    }

    /**
     * Group players whose names look like the same person entered under
     * slightly different spelling - case, whitespace, accents (Müller vs
     * Mueller), a small typo, firstname/surname swapped (Schindler
     * Bärbel vs Bärbel Schindler), or a common German nickname (Spänle
     * Heiner vs Spänle Heinrich) - so they can be spotted and cleaned up.
     * Only players with at least one likely match are included.
     *
     * @return SupportCollection<int, Collection<int, Player>>
     */
    public static function possibleDuplicateGroups(): SupportCollection
    {
        $players = static::orderBy('name')->get(['id', 'name']);

        $normalized = $players->mapWithKeys(fn (self $player): array => [
            $player->id => self::canonicalizeNicknames(
                Str::of($player->name)->trim()->lower()->ascii('de')->toString()
            ),
        ]);

        $wordSorted = $normalized->map(fn (string $name): string => self::sortWords($name));

        /** @var array<int, int> $parent */
        $parent = $players->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $id])->all();

        $ids = $players->pluck('id')->all();

        foreach ($ids as $index => $idA) {
            foreach (array_slice($ids, $index + 1) as $idB) {
                if (self::namesLikelyMatch($normalized[$idA], $normalized[$idB])
                    || self::namesLikelyMatch($wordSorted[$idA], $wordSorted[$idB])) {
                    self::union($parent, $idA, $idB);
                }
            }
        }

        return $players
            ->groupBy(fn (self $player): int => self::find($parent, $player->id))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->values();
    }

    private static function namesLikelyMatch(string $a, string $b): bool
    {
        $distance = levenshtein($a, $b);
        $threshold = min(strlen($a), strlen($b)) >= 6 ? 2 : 1;

        return $distance <= $threshold;
    }

    /**
     * Alphabetically sort the words of a name, so e.g. "schindler baerbel"
     * and "baerbel schindler" become comparable - catches firstname/surname
     * entered in swapped order.
     */
    private static function sortWords(string $name): string
    {
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($words);

        return implode(' ', $words);
    }

    /**
     * Common German nickname -> full given-name equivalents (already
     * lowercased and transliterated, matching the ascii('de') normalization
     * applied before this runs), so e.g. "Heiner" and "Heinrich" are
     * recognized as the same name even though they're too different for
     * namesLikelyMatch()'s typo tolerance. Not exhaustive - just the most
     * common ones - so this catches some but not all nickname variations.
     *
     * @var array<string, string>
     */
    private const array NICKNAMES = [
        'heiner' => 'heinrich',
        'heinz' => 'heinrich',
        'fritz' => 'friedrich',
        'willi' => 'wilhelm',
        'sepp' => 'josef',
        'seppl' => 'josef',
        'schorsch' => 'georg',
        'bernd' => 'bernhard',
        'berni' => 'bernhard',
        'andi' => 'andreas',
        'toni' => 'anton',
        'michi' => 'michael',
        'michl' => 'michael',
        'gaby' => 'gabriele',
        'kathi' => 'katharina',
        'kaethe' => 'katharina',
        'grete' => 'margarete',
        'gretel' => 'margarete',
        'lisa' => 'elisabeth',
        'liesl' => 'elisabeth',
        'betti' => 'elisabeth',
        'franzi' => 'franziska',
        'moni' => 'monika',
        'susi' => 'susanne',
        'didi' => 'dieter',
        'ede' => 'eduard',
        'fips' => 'philipp',
        'gustl' => 'gustav',
        'resi' => 'theresia',
        'uli' => 'ulrich',
        'wolfi' => 'wolfgang',
        'manni' => 'manfred',
        'rudi' => 'rudolf',
        'bene' => 'benedikt',
        'benni' => 'benedikt',
        'steffi' => 'stefanie',
    ];

    /**
     * Replaces any word that's a known nickname with its full form, so
     * name matching can see past it.
     */
    private static function canonicalizeNicknames(string $name): string
    {
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_map(fn (string $word): string => self::NICKNAMES[$word] ?? $word, $words);

        return implode(' ', $words);
    }

    /**
     * @param  array<int, int>  $parent
     */
    private static function find(array &$parent, int $id): int
    {
        if ($parent[$id] !== $id) {
            $parent[$id] = self::find($parent, $parent[$id]);
        }

        return $parent[$id];
    }

    /**
     * @param  array<int, int>  $parent
     */
    private static function union(array &$parent, int $a, int $b): void
    {
        $rootA = self::find($parent, $a);
        $rootB = self::find($parent, $b);

        if ($rootA !== $rootB) {
            $parent[$rootB] = $rootA;
        }
    }
}

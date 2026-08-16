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
     * Mueller), a small typo, or firstname/surname swapped (Schindler
     * Bärbel vs Bärbel Schindler) - so they can be spotted and cleaned up.
     * Only players with at least one likely match are included.
     *
     * @return SupportCollection<int, Collection<int, Player>>
     */
    public static function possibleDuplicateGroups(): SupportCollection
    {
        $players = static::orderBy('name')->get(['id', 'name']);

        $normalized = $players->mapWithKeys(fn (self $player): array => [
            $player->id => Str::of($player->name)->trim()->lower()->ascii('de')->toString(),
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
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        sort($words);

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

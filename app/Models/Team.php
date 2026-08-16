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

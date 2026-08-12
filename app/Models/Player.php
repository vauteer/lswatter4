<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    /** @use HasFactory<Factory<Player>> */
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
}

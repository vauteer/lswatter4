<?php

namespace App\Models;

use App\ActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $at
 * @property int $user_id
 * @property int $action_type
 * @property int|null $table_type
 * @property int|null $row_id
 * @property string|null $old_values
 */
#[Fillable(['at', 'user_id', 'action_type', 'table_type', 'row_id', 'old_values'])]
#[WithoutTimestamps]
class Tracing extends Model
{
    /** @use HasFactory<Factory<Tracing>> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Tracing>  $query
     */
    #[Scope]
    protected function actionType(Builder $query, ActionType $actionType): void
    {
        $query->where('action_type', $actionType);
    }
}

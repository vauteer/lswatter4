<?php

namespace App\Http\Resources;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fixture
 */
class FixtureResource extends JsonResource
{
    /**
     * Disable the default "data" wrapping. This resource is only ever
     * consumed as an Inertia prop, not as a top-level API response, so
     * the wrapper would just be noise the frontend has to unwrap.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team1' => (string) $this->team1,
            'team2' => (string) $this->team2,
            'round' => $this->round,
            'tableNumber' => $this->table_number,
            'score' => $this->score,
            'scoreGames' => "{$this->team1_won}:{$this->team2_won}",
            'scorePoints' => "{$this->team1_points}:{$this->team2_points}",
            'games' => $this->score ? explode(' ', $this->score) : [],
            'editable' => $request->user()?->can('update', $this->resource) ?? false,
        ];
    }
}

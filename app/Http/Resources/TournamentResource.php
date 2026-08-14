<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Disable the default "data" wrapping. This resource is only ever
     * consumed as an Inertia prop (paginated or single), not as a
     * top-level API response, so the wrapper would just be noise the
     * frontend has to unwrap.
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
            'name' => $this->name,
            'start' => $this->start->format('Y-m-d\TH:i'),
            'rounds' => $this->rounds,
            'games' => $this->games,
            'winpoints' => $this->winpoints,
            'private' => $this->private,
            'creator' => $this->creator->name,
            'modifiable' => $request->user()->can('update', $this->resource),
            'deletable' => $request->user()->can('delete', $this->resource),
        ];
    }
}

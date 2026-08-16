<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'admin' => $this->admin,
            'created_at' => $this->created_at,
            'impersonatable' => $request->user()->isAdmin()
                && $request->user()->isNot($this->resource)
                && ! $this->admin,
            'modifiable' => $request->user()->can('update', $this->resource),
            'deletable' => $request->user()->can('delete', $this->resource),
        ];
    }
}

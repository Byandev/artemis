<?php

namespace Modules\TaskManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Models\Space;

/**
 * @mixin Space
 */
class SpaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'color' => $this->color,
            'position' => $this->position,
            'metadata' => $this->metadata,
            'archived' => $this->archived_at !== null,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'role' => $this->roleFor($request->user())?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

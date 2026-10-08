<?php

namespace Modules\TaskManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Models\Folder;

/**
 * @mixin Folder
 */
class FolderResource extends JsonResource
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
            'space_id' => $this->space_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'position' => $this->position,
            'metadata' => $this->metadata,
            'archived' => $this->archived_at !== null,
            'lists' => TaskListResource::collection($this->whenLoaded('lists')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace Modules\TaskManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Models\TaskStatus;

/**
 * @mixin TaskStatus
 */
class TaskStatusResource extends JsonResource
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
            'slug' => $this->slug,
            'color' => $this->color,
            'type' => $this->type->value,
            'is_complete' => $this->type->isComplete(),
            'position' => $this->position,
            'is_default' => $this->is_default,
        ];
    }
}

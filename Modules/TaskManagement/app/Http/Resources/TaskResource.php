<?php

namespace Modules\TaskManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Models\Task;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'list_id' => $this->task_list_id,
            'status_id' => $this->task_status_id,
            'parent_id' => $this->parent_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'ticket' => $this->ticketIdentifier(),
            'ticket_code' => $this->ticket_code,
            'ticket_number' => $this->ticket_number,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'position' => $this->position,
            'estimate_minutes' => $this->estimate_minutes,
            'start_at' => $this->start_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed' => $this->completed_at !== null,
            'archived' => $this->archived_at !== null,
            'metadata' => $this->metadata,
            'subtasks_count' => $this->whenCounted('subtasks'),
            'status' => new TaskStatusResource($this->whenLoaded('status')),
            'list' => new TaskListResource($this->whenLoaded('list')),
            'parent' => new self($this->whenLoaded('parent')),
            'subtasks' => self::collection($this->whenLoaded('subtasks')),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'assignees' => UserSummaryResource::collection($this->whenLoaded('assignees')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

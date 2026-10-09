<?php

namespace Modules\TaskManagement\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskList;

trait ValidatesTaskAttributes
{
    /**
     * Get the rules shared by creating and updating a task.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function taskAttributeRules(Space $space, TaskList $list): array
    {
        return [
            'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'position' => ['sometimes', 'integer', 'min:0'],
            'estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'status_id' => [
                'sometimes',
                'integer',
                Rule::exists('task_statuses', 'id')->where('space_id', $space->id),
            ],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', Rule::in($this->assignableUserIds($space))],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => [
                'integer',
                Rule::exists('task_labels', 'id')->where('space_id', $space->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('task_list_id', $list->id),
            ],
        ];
    }

    /**
     * Get the custom validation messages shared by creating and updating a task.
     *
     * @return array<string, string>
     */
    protected function taskAttributeMessages(): array
    {
        return [
            'status_id.exists' => __('The selected status does not belong to this space.'),
            'label_ids.*.in' => __('The selected label does not belong to this space.'),
            'label_ids.*.exists' => __('The selected label does not belong to this space.'),
            'assignee_ids.*.in' => __('The selected assignee is not a member of this space.'),
            'parent_id.exists' => __('The selected parent task is not in the same list.'),
        ];
    }

    /**
     * Get the ids of everyone who may be assigned a task in the space.
     *
     * @return array<int, int>
     */
    private function assignableUserIds(Space $space): array
    {
        return $space->members()
            ->pluck('users.id')
            ->push($space->owner_id)
            ->unique()
            ->values()
            ->all();
    }
}

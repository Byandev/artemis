<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Http\Requests\Concerns\ValidatesTaskAttributes;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;

class UpdateTaskRequest extends FormRequest
{
    use ValidatesTaskAttributes;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Task $task */
        $task = $this->route('task');
        $space = $task->list->space;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
            'list_id' => [
                'sometimes',
                'integer',
                Rule::exists('task_lists', 'id')->where('space_id', $space->id),
            ],
            ...$this->taskAttributeRules($space, $this->targetList($task)),
            'parent_id' => [
                'nullable',
                'integer',
                'different:id',
                Rule::exists('tasks', 'id')
                    ->where('task_list_id', $this->targetList($task)->id)
                    ->whereNot('id', $task->id),
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->taskAttributeMessages(),
            'list_id.exists' => __('The selected list does not belong to this space.'),
        ];
    }

    /**
     * Get the list the task will live in once the request is applied.
     */
    private function targetList(Task $task): TaskList
    {
        if (! $this->has('list_id')) {
            return $task->list;
        }

        return TaskList::query()
            ->where('space_id', $task->list->space_id)
            ->find($this->integer('list_id')) ?? $task->list;
    }
}

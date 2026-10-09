<?php

namespace Modules\TaskManagement\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Queries\TaskIndexQuery;

class IndexTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.space_id' => ['sometimes', 'integer'],
            'filter.folder_id' => ['sometimes', 'integer'],
            'filter.list_id' => ['sometimes', 'integer'],
            'filter.status_id' => ['sometimes', 'integer'],
            'filter.status_type' => ['sometimes', Rule::enum(TaskStatusType::class)],
            'filter.priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'filter.assignee_id' => ['sometimes', 'integer'],
            'filter.label_id' => ['sometimes', 'integer'],
            'filter.parent_id' => ['sometimes', 'string'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'filter.due_after' => ['sometimes', 'date'],
            'filter.due_before' => ['sometimes', 'date'],
            'filter.completed' => ['sometimes', 'boolean'],
            'filter.archived' => ['sometimes', Rule::in(TaskIndexQuery::ARCHIVE_MODES)],
            'filter.meta' => ['sometimes', 'array'],
            'filter.meta.*' => ['nullable', 'string'],
            'sort' => ['sometimes', 'string', $this->allowedList(TaskIndexQuery::SORTS, stripDirection: true)],
            'include' => ['sometimes', 'string', $this->allowedList(TaskIndexQuery::INCLUDES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get the page size the client asked for.
     */
    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }

    /**
     * Get the parameters the task query understands.
     *
     * @return array{filter?: array<string, mixed>, sort?: string, include?: string}
     */
    public function queryParameters(): array
    {
        /** @var array{filter?: array<string, mixed>, sort?: string, include?: string} $parameters */
        $parameters = $this->safe()->only(['filter', 'sort', 'include']);

        return $parameters;
    }

    /**
     * Build a rule that rejects any comma separated value outside the allow list.
     *
     * @param  array<int, string>  $allowed
     */
    private function allowedList(array $allowed, bool $stripDirection = false): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowed, $stripDirection): void {
            foreach (TaskIndexQuery::parseList((string) $value) as $entry) {
                $candidate = $stripDirection ? TaskIndexQuery::sortField($entry) : $entry;

                if (! in_array($candidate, $allowed, true)) {
                    $fail(__('The :attribute value [:entry] is not supported. Allowed values are: :allowed.', [
                        'attribute' => $attribute,
                        'entry' => $entry,
                        'allowed' => implode(', ', $allowed),
                    ]));

                    return;
                }
            }
        };
    }
}

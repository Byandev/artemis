<?php

namespace Modules\TaskManagement\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Models\Task;

/**
 * Applies the filter, sort, include and pagination parameters of the task index
 * endpoints to a task query.
 */
class TaskIndexQuery
{
    /**
     * The columns a client may sort by.
     *
     * @var array<int, string>
     */
    public const SORTS = [
        'position',
        'name',
        'priority',
        'due_at',
        'start_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The relationships a client may eager load.
     *
     * @var array<int, string>
     */
    public const INCLUDES = [
        'status',
        'list',
        'list.folder',
        'list.space',
        'parent',
        'subtasks',
        'creator',
        'assignees',
        'labels',
    ];

    /**
     * The archive modes a client may ask for.
     *
     * @var array<int, string>
     */
    public const ARCHIVE_MODES = ['without', 'with', 'only'];

    /**
     * Build the task query from validated index parameters.
     *
     * @param  Builder<Task>  $query
     * @param  array{filter?: array<string, mixed>, sort?: string, include?: string}  $parameters
     * @return Builder<Task>
     */
    public function apply(Builder $query, array $parameters): Builder
    {
        $this->applyFilters($query, $parameters['filter'] ?? []);
        $this->applyIncludes($query, $parameters['include'] ?? null);
        $this->applySorts($query, $parameters['sort'] ?? null);

        return $query;
    }

    /**
     * Split a comma separated list into trimmed, non-empty values.
     *
     * @return array<int, string>
     */
    public static function parseList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Strip the leading direction marker from a sort field.
     */
    public static function sortField(string $sort): string
    {
        return ltrim($sort, '-');
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when(isset($filters['space_id']), fn (Builder $query) => $query->whereHas(
            'list', fn (Builder $list) => $list->where('space_id', $filters['space_id'])
        ));

        $query->when(isset($filters['folder_id']), fn (Builder $query) => $query->whereHas(
            'list', fn (Builder $list) => $list->where('folder_id', $filters['folder_id'])
        ));

        $query->when(isset($filters['list_id']), fn (Builder $query) => $query->where('task_list_id', $filters['list_id']));

        $query->when(isset($filters['status_id']), fn (Builder $query) => $query->where('task_status_id', $filters['status_id']));

        $query->when(isset($filters['status_type']), fn (Builder $query) => $query->whereHas(
            'status', fn (Builder $status) => $status->where('type', $filters['status_type'])
        ));

        $query->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']));

        $query->when(isset($filters['assignee_id']), fn (Builder $query) => $query->whereHas(
            'assignees', fn (Builder $assignees) => $assignees->whereKey($filters['assignee_id'])
        ));

        $query->when(isset($filters['label_id']), fn (Builder $query) => $query->whereHas(
            'labels', fn (Builder $labels) => $labels->whereKey($filters['label_id'])
        ));

        $query->when(isset($filters['search']), function (Builder $query) use ($filters): void {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';

            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        });

        $query->when(isset($filters['due_after']), fn (Builder $query) => $query->where('due_at', '>=', $filters['due_after']));
        $query->when(isset($filters['due_before']), fn (Builder $query) => $query->where('due_at', '<=', $filters['due_before']));

        $query->when(isset($filters['parent_id']), function (Builder $query) use ($filters): void {
            $parent = $filters['parent_id'];

            $parent === 'null'
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', $parent);
        });

        $query->when(isset($filters['completed']), fn (Builder $query) => filter_var($filters['completed'], FILTER_VALIDATE_BOOLEAN)
            ? $query->whereNotNull('completed_at')
            : $query->whereNull('completed_at'));

        foreach ($filters['meta'] ?? [] as $key => $value) {
            $query->where("metadata->{$key}", $value);
        }

        match ($filters['archived'] ?? 'without') {
            'with' => null,
            'only' => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at'),
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyIncludes(Builder $query, ?string $include): void
    {
        $includes = self::parseList($include);

        if ($includes !== []) {
            $query->with($includes);
        }
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applySorts(Builder $query, ?string $sort): void
    {
        $sorts = self::parseList($sort);

        if ($sorts === []) {
            $query->orderBy('position')->orderBy('id');

            return;
        }

        foreach ($sorts as $entry) {
            $direction = str_starts_with($entry, '-') ? 'desc' : 'asc';
            $field = self::sortField($entry);

            $field === 'priority'
                ? $this->sortByPriorityWeight($query, $direction)
                : $query->orderBy($field, $direction);
        }

        $query->orderBy('id');
    }

    /**
     * Order by the domain weight of the priority rather than its alphabetical value.
     *
     * @param  Builder<Task>  $query
     */
    private function sortByPriorityWeight(Builder $query, string $direction): void
    {
        $cases = [];
        $bindings = [];

        foreach (TaskPriority::cases() as $priority) {
            $cases[] = 'when ? then ?';
            $bindings[] = $priority->value;
            $bindings[] = $priority->weight();
        }

        $query->orderByRaw(
            'case priority '.implode(' ', $cases).' else 0 end '.($direction === 'desc' ? 'desc' : 'asc'),
            $bindings,
        );
    }
}

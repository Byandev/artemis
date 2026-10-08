<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\TaskManagement\Http\Requests\IndexTaskRequest;
use Modules\TaskManagement\Http\Requests\StoreTaskRequest;
use Modules\TaskManagement\Http\Requests\UpdateTaskRequest;
use Modules\TaskManagement\Http\Resources\TaskResource;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Models\TaskStatus;
use Modules\TaskManagement\Queries\TaskIndexQuery;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TaskController extends Controller
{
    public function __construct(private readonly TaskIndexQuery $indexQuery) {}

    /**
     * List every task in this workspace the authenticated user can see, filtered and sorted on demand.
     */
    public function index(Workspace $workspace, IndexTaskRequest $request): AnonymousResourceCollection
    {
        $query = Task::query()
            ->where('workspace_id', $workspace->id)
            ->visibleTo($request->user());

        $tasks = $this->indexQuery
            ->apply($query, $request->queryParameters())
            ->paginate($request->perPage())
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /**
     * List the tasks of a single list, using the same filters as the global index.
     */
    public function indexForList(Workspace $workspace, IndexTaskRequest $request, TaskList $list): AnonymousResourceCollection
    {
        Gate::authorize('view', $list);

        $tasks = $this->indexQuery
            ->apply(Task::query()->where('task_list_id', $list->id), $request->queryParameters())
            ->paginate($request->perPage())
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /**
     * Create a task inside a list.
     */
    public function store(Workspace $workspace, StoreTaskRequest $request, TaskList $list): JsonResponse
    {
        $status = $this->resolveStatus($request->integer('status_id') ?: null, $list);

        $task = DB::transaction(function () use ($request, $list, $status): Task {
            $task = new Task($request->safe()->only([
                'name', 'description', 'priority', 'estimate_minutes', 'start_at', 'due_at', 'metadata',
            ]));

            $task->task_list_id = $list->id;
            $task->task_status_id = $status->id;
            $task->parent_id = $request->input('parent_id');
            $task->created_by = $request->user()->id;
            $task->position = $request->integer('position', $this->nextPosition($list));
            $task->completed_at = $status->type->isComplete() ? now() : null;
            $task->save();

            $this->syncRelations($request->safe()->all(), $task);

            return $task;
        });

        return TaskResource::make($this->withRelations($task))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Show a single task with its relationships.
     */
    public function show(Workspace $workspace, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make(
            $this->withRelations($task)->load(['parent', 'subtasks.status'])
        );
    }

    /**
     * Update a task, including moving it between lists and statuses of the same space.
     */
    public function update(Workspace $workspace, UpdateTaskRequest $request, Task $task): TaskResource
    {
        DB::transaction(function () use ($request, $task): void {
            $task->fill($request->safe()->only([
                'name', 'description', 'priority', 'position', 'estimate_minutes', 'start_at', 'due_at', 'metadata',
            ]));

            if ($request->has('list_id')) {
                $task->task_list_id = $request->integer('list_id');
            }

            if ($request->has('parent_id')) {
                $task->parent_id = $request->input('parent_id');
            }

            if ($request->has('status_id')) {
                $status = $this->resolveStatus($request->integer('status_id'), $task->list);

                $task->task_status_id = $status->id;
                $task->completed_at = $status->type->isComplete() ? ($task->completed_at ?? now()) : null;
            }

            if ($request->has('archived')) {
                $task->archived_at = $request->boolean('archived') ? now() : null;
            }

            $task->save();

            $this->syncRelations($request->safe()->all(), $task);
        });

        return TaskResource::make($this->withRelations($task->refresh()));
    }

    /**
     * Delete a task together with its subtasks and their files.
     *
     * The files are purged first because `tasks.parent_id` cascades in the
     * database: the subtask rows go without an Eloquent event, and the media
     * library's disk cleanup hangs off that event.
     */
    public function destroy(Workspace $workspace, Task $task): Response
    {
        Gate::authorize('delete', $task);

        DB::transaction(function () use ($task): void {
            Attachment::purgeForTasks(Task::query()->whereKey($task->subtreeIds()));

            $task->delete();
        });

        return response()->noContent();
    }

    /**
     * Resolve the status a task should use, falling back to the default of its space.
     */
    private function resolveStatus(?int $statusId, TaskList $list): TaskStatus
    {
        if ($statusId !== null) {
            return TaskStatus::query()
                ->where('space_id', $list->space_id)
                ->findOrFail($statusId);
        }

        $default = $list->space->statuses()
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->first();

        if ($default === null) {
            throw ValidationException::withMessages([
                'status_id' => __('This space has no statuses yet, so a status must be given.'),
            ]);
        }

        return $default;
    }

    /**
     * Replace the assignees and labels of a task when the request supplies them.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncRelations(array $validated, Task $task): void
    {
        if (array_key_exists('assignee_ids', $validated)) {
            $task->assignees()->sync($validated['assignee_ids']);
        }

        if (array_key_exists('label_ids', $validated)) {
            $task->labels()->sync($validated['label_ids']);
        }
    }

    /**
     * Load the relationships a single-task response always exposes.
     */
    private function withRelations(Task $task): Task
    {
        return $task->load(['status', 'list', 'assignees', 'labels', 'creator']);
    }

    /**
     * Get the position that places a new task after the existing ones in the list.
     */
    private function nextPosition(TaskList $list): int
    {
        return (int) $list->tasks()->max('position') + 1;
    }
}

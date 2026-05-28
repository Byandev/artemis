<?php

namespace Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\TaskManagement\Models\Task;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->ensureWorkspaceAccess($request, $workspace);

        $baseTaskQuery = $workspace->tasks()
            ->with([
                'creator:id,name,email',
                'assignees:id,name,email',
                'comments' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->oldest(),
            ]);

        $tasks = QueryBuilder::for($baseTaskQuery)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $search = trim((string) $value);

                    if ($search === '') {
                        return;
                    }

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('assignees', function ($query) use ($search) {
                                $query
                                    ->where('users.name', 'like', "%{$search}%")
                                    ->orWhere('users.email', 'like', "%{$search}%");
                            });
                    });
                }),
                AllowedFilter::callback('status', function ($query, $value) {
                    match ($value) {
                        'todo' => $query
                            ->whereDoesntHave('assignees', function ($query) {
                                $query->where('task_assignees.status', 'in_progress');
                            })
                            ->where(function ($query) {
                                $query
                                    ->whereDoesntHave('assignees')
                                    ->orWhereHas('assignees', function ($query) {
                                        $query->where('task_assignees.status', '!=', 'done');
                                    });
                            }),
                        'in_progress' => $query->whereHas('assignees', function ($query) {
                            $query->where('task_assignees.status', 'in_progress');
                        }),
                        'done' => $query
                            ->whereHas('assignees')
                            ->whereDoesntHave('assignees', function ($query) {
                                $query->where('task_assignees.status', '!=', 'done');
                            }),
                        default => null,
                    };
                }),
            ])
            ->latest()
            ->get();

        $taskStatusCounts = $workspace->tasks()
            ->with('assignees:id')
            ->get()
            ->countBy(fn (Task $task) => $this->taskStatus($task));

        $workspaceMembers = $workspace->users()
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('workspaces/tasks/index', [
            'workspace' => $workspace,
            'tasks' => $tasks,
            'taskStatusCounts' => [
                'todo' => $taskStatusCounts->get('todo', 0),
                'in_progress' => $taskStatusCounts->get('in_progress', 0),
                'done' => $taskStatusCounts->get('done', 0),
            ],
            'workspaceMembers' => $workspaceMembers,
            'query' => [
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->ensureWorkspaceAccess($request, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'recurrence' => ['required', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
            'due_date' => ['nullable', 'required_unless:recurrence,none', 'date'],
            'recurring_until' => ['nullable', 'date', 'after_or_equal:due_date'],
            'assignees' => ['array'],
            'assignees.*' => [
                'integer',
                Rule::exists('workspace_user', 'user_id')
                    ->where('workspace_id', $workspace->id),
            ],
        ]);

        $task = $workspace->tasks()->create([
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'recurrence' => $validated['recurrence'],
            'recurring_until' => $validated['recurrence'] === 'none'
                ? null
                : ($validated['recurring_until'] ?? null),
        ]);

        $assigneeIds = collect($validated['assignees'] ?? [])
            ->unique()
            ->values()
            ->all();

        if ($assigneeIds !== []) {
            $task->assignees()->attach(
                collect($assigneeIds)
                    ->mapWithKeys(fn ($id) => [$id => ['status' => 'todo']])
                    ->all()
            );
        }

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    public function updateStatus(Request $request, Workspace $workspace, Task $task)
    {
        $this->ensureWorkspaceAccess($request, $workspace);
        $this->ensureTaskBelongsToWorkspace($task, $workspace);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('task_assignees', 'user_id')
                    ->where('task_id', $task->id),
            ],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
        ]);

        $task->assignees()->updateExistingPivot($validated['user_id'], [
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Task status updated.');
    }

    public function updateAllStatuses(Request $request, Workspace $workspace, Task $task)
    {
        $this->ensureWorkspaceAccess($request, $workspace);
        $this->ensureTaskBelongsToWorkspace($task, $workspace);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::exists('task_assignees', 'user_id')
                    ->where('task_id', $task->id),
            ],
        ]);

        $task->assignees()
            ->newPivotStatement()
            ->where('task_id', $task->id)
            ->whereIn('user_id', $validated['user_ids'])
            ->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Selected assignees updated.');
    }

    public function update(Request $request, Workspace $workspace, Task $task)
    {
        $this->ensureWorkspaceAccess($request, $workspace);
        $this->ensureTaskBelongsToWorkspace($task, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'recurrence' => ['required', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
            'due_date' => ['nullable', 'required_unless:recurrence,none', 'date'],
            'recurring_until' => ['nullable', 'date', 'after_or_equal:due_date'],
            'assignees' => ['array'],
            'assignees.*' => [
                'integer',
                Rule::exists('workspace_user', 'user_id')
                    ->where('workspace_id', $workspace->id),
            ],
        ]);

        $task->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'recurrence' => $validated['recurrence'],
            'recurring_until' => $validated['recurrence'] === 'none'
                ? null
                : ($validated['recurring_until'] ?? null),
        ]);

        $existingStatuses = $task->assignees()
            ->pluck('task_assignees.status', 'users.id');

        $assigneeIds = collect($validated['assignees'] ?? [])
            ->unique()
            ->values();

        $task->assignees()->sync(
            $assigneeIds
                ->mapWithKeys(fn ($id) => [
                    $id => ['status' => $existingStatuses->get($id, 'todo')],
                ])
                ->all()
        );

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    public function storeComment(Request $request, Workspace $workspace, Task $task)
    {
        $this->ensureWorkspaceAccess($request, $workspace);
        $this->ensureTaskBelongsToWorkspace($task, $workspace);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect()->back()->with('success', 'Comment added.');
    }

    public function destroy(Request $request, Workspace $workspace, Task $task)
    {
        $this->ensureWorkspaceAccess($request, $workspace);
        $this->ensureTaskBelongsToWorkspace($task, $workspace);

        $task->delete();

        return redirect()->back()->with('success', 'Task deleted successfully.');
    }

    private function ensureTaskBelongsToWorkspace(Task $task, Workspace $workspace): void
    {
        if ((int) $task->workspace_id !== (int) $workspace->id) {
            abort(404);
        }
    }

    private function ensureWorkspaceAccess(Request $request, Workspace $workspace): void
    {
        if (! $workspace->task_management_module_enabled) {
            abort(403, 'Task Management is not enabled for this workspace.');
        }

        $user = $request->user();

        if (! $user->isMemberOf($workspace) && ! $user->ownsWorkspace($workspace)) {
            abort(403);
        }
    }

    private function taskStatus(Task $task): string
    {
        if (
            $task->assignees->isNotEmpty() &&
            $task->assignees->every(fn ($assignee) => $assignee->pivot->status === 'done')
        ) {
            return 'done';
        }

        if ($task->assignees->contains(fn ($assignee) => $assignee->pivot->status === 'in_progress')) {
            return 'in_progress';
        }

        return 'todo';
    }
}

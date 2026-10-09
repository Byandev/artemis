<?php

namespace Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\TaskManagement\Models\Task;

/**
 * The two Task Management pages. Both are shells: the board and the task detail
 * read everything from the JSON endpoints under /api/workspaces/{slug}/task-management,
 * so there is no second, page-only data path.
 *
 * Module toggle, membership and "View Tasks" are enforced by
 * EnsureTaskManagementAccess on the routes.
 */
class TaskManagementController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/tasks/index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
        ]);
    }

    public function show(Workspace $workspace, Task $task): Response
    {
        Gate::authorize('view', $task);

        return Inertia::render('workspaces/tasks/show', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'taskId' => $task->id,
        ]);
    }
}

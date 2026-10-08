<?php

namespace Modules\TaskManagement\Http\Middleware;

use App\Enums\Permission;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;
use Symfony\Component\HttpFoundation\Response;

/**
 * The workspace-level gate in front of every Task Management page and endpoint.
 *
 * Space roles (owner/admin/member/viewer) decide what someone may do inside a
 * space, through the policies. This decides whether they reach the module at
 * all: the workspace must have it switched on, and the user must belong to the
 * workspace and hold "View Tasks" -- plus "Manage Tasks" for anything that
 * writes. The module toggle is checked here as well as
 * in the sidebar because workspace owners hold every permission.
 *
 * Records are addressed by id under the workspace prefix (`/tasks/{task}`), so
 * every bound record is also checked against the workspace in the URL -- one
 * from another workspace answers 404, exactly as a missing id does.
 */
class EnsureTaskManagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        abort_unless($workspace instanceof Workspace, 404);
        abort_unless($workspace->task_management_module_enabled, 404);

        $user = $request->user();

        abort_unless($user->isMemberOf($workspace), 403);
        abort_unless($user->hasPermission(Permission::ViewTasks, $workspace), 403);

        // "View Tasks" is read-only. Every change -- a space, list, status,
        // member, task, comment or file -- also needs "Manage Tasks", on top
        // of whatever the user's role in that space allows.
        abort_if(
            ! $request->isMethodSafe() && ! $user->hasPermission(Permission::ManageTasks, $workspace),
            403,
            'You need the Manage Tasks permission to make changes.',
        );

        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof BelongsToTaskWorkspace && $parameter->taskWorkspaceId() !== $workspace->id) {
                abort(404, 'Not Found');
            }
        }

        return $next($request);
    }
}

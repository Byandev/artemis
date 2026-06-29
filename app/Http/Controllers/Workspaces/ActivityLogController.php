<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    /**
     * Workspace-scoped activity log. Visible to workspace admins/owners only.
     */
    public function index(Request $request, Workspace $workspace)
    {
        abort_unless($request->user()->isAdminOf($workspace), 403);

        $base = ActivityLog::query()->where('workspace_id', $workspace->id);

        return Inertia::render('workspaces/activity-logs/index', [
            'workspace' => $workspace,
            'logs' => $this->paginateActivityLogs($base, $request),
            'summary' => $this->activityLogSummary(
                ActivityLog::query()->where('workspace_id', $workspace->id),
                $request,
            ),
            'options' => $this->activityLogFilterOptions(),
            'filters' => $request->input('filter', []),
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->input('per_page'),
            ],
        ]);
    }
}

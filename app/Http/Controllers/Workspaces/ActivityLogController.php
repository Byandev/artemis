<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    /**
     * Workspace-scoped activity log page. Visible to workspace admins/owners
     * only. The page is a shell — the logs and summary are fetched from
     * API\Workspace\ActivityLogController.
     */
    public function index(Request $request, Workspace $workspace)
    {
        abort_unless($request->user()->isAdminOf($workspace), 403);

        return Inertia::render('workspaces/activity-logs/index', [
            'workspace' => $workspace,
            'options' => $this->activityLogFilterOptions(),
            'filters' => $request->input('filter', []),
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->input('per_page'),
            ],
        ]);
    }
}

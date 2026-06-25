<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    /**
     * Global, cross-workspace activity log. Guarded by the `admin` middleware.
     * The optional `filter[workspace_id]` lets admins narrow to one tenant.
     */
    public function index(Request $request)
    {
        return Inertia::render('admin/activity-logs/index', [
            'logs' => $this->paginateActivityLogs(ActivityLog::query(), $request, withWorkspace: true),
            'summary' => $this->activityLogSummary(ActivityLog::query(), $request),
            'options' => $this->activityLogFilterOptions(),
            'filters' => $request->input('filter', []),
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->input('per_page'),
            ],
        ]);
    }
}

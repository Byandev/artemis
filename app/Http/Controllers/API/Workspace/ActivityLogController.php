<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace-scoped activity log over XHR. The table and the stat cards are
 * separate endpoints so each loads and refreshes on its own. Visible to
 * workspace admins/owners only.
 */
class ActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isAdminOf($workspace), 403);

        return response()->json(
            $this->paginateActivityLogs($this->scoped($workspace), $request)
        );
    }

    public function summary(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isAdminOf($workspace), 403);

        return response()->json(
            $this->activityLogSummary($this->scoped($workspace), $request)
        );
    }

    private function scoped(Workspace $workspace): Builder
    {
        return ActivityLog::query()->where('workspace_id', $workspace->id);
    }
}

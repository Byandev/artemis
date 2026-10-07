<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global, cross-workspace activity log over XHR. Super admins only — checked
 * here rather than via the `admin` middleware, which redirects instead of
 * returning a JSON 403. `filter[workspace_id]` narrows to one tenant.
 */
class ActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_super_admin, 403);

        return response()->json(
            $this->paginateActivityLogs(ActivityLog::query(), $request, withWorkspace: true)
        );
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_super_admin, 403);

        return response()->json(
            $this->activityLogSummary(ActivityLog::query(), $request)
        );
    }
}

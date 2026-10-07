<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsActivityLogQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminActivityLogController extends Controller
{
    use BuildsActivityLogQuery;

    /**
     * Global, cross-workspace activity log page. Guarded by the `admin`
     * middleware. The page is a shell — the logs and summary are fetched from
     * API\Admin\ActivityLogController.
     */
    public function index(Request $request)
    {
        return Inertia::render('admin/activity-logs/index', [
            'options' => $this->activityLogFilterOptions(),
            'filters' => $request->input('filter', []),
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->input('per_page'),
            ],
        ]);
    }
}

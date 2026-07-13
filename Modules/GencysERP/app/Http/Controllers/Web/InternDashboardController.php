<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Queries\InternDashboardQuery;

class InternDashboardController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysInternDailyRecords->value, $workspace);

        $internIds = array_filter((array) $request->input('filter.gencys_intern_id', []));
        $date = $request->input('filter.date') ?: null;

        $data = (new InternDashboardQuery($workspace, $internIds, $date))->get();

        $interns = Intern::where('workspace_id', $workspace->id)
            ->whereIn('id', GencysInternDailyRecord::where('workspace_id', $workspace->id)->select('gencys_intern_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return Inertia::render('workspaces/gencys/intern-dashboard/index', [
            'workspace' => $workspace,
            'interns' => $interns,
            'view' => $data,
            'filters' => [
                'gencys_intern_id' => array_values(array_map('strval', $internIds)),
                // Resolved report date so the date picker reflects what's shown.
                'date' => $data['report_date'],
            ],
        ]);
    }
}

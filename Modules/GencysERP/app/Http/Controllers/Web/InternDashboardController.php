<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Queries\AdvertiserDashboardQuery;

class InternDashboardController extends Controller
{
    use AuthorizesRequests;

    /** Inertia page shell + first paint (default date). */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysInternDailyRecords->value, $workspace);

        [$data, $filters] = $this->build($request, $workspace);

        return Inertia::render('workspaces/gencys/intern-dashboard/index', [
            'workspace' => $workspace,
            'view' => $data,
            'filters' => $filters,
        ]);
    }

    /** JSON data endpoint the page fetches (axios) when the date changes. */
    public function data(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewGencysInternDailyRecords->value, $workspace);

        [$data, $filters] = $this->build($request, $workspace);

        return response()->json([
            'view' => $data,
            'filters' => $filters,
        ]);
    }

    /**
     * Run the dashboard query for the requested date and return the
     * [$view, $filters] pair shared by both the Inertia and JSON entry points.
     *
     * @return array{0: array, 1: array}
     */
    private function build(Request $request, Workspace $workspace): array
    {
        $date = $request->input('filter.date') ?: null;

        $data = (new AdvertiserDashboardQuery($workspace, [], $date))->get();

        $filters = [
            // Echo the resolved date so the picker reflects what's shown.
            'date' => $data['date'],
        ];

        return [$data, $filters];
    }
}

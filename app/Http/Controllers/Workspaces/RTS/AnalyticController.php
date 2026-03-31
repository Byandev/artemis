<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\RtsCxQuery;
use App\Queries\RtsDeliveryAttemptsQuery;
use App\Queries\RtsLocationQuery;
use App\Queries\RtsOrderItemQuery;
use App\Queries\RtsPriceQuery;
use App\Queries\RtsConfirmedByQuery;
use App\Queries\RtsRiderQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticController extends Controller
{
    public function index(Workspace $workspace)
    {
        return Inertia::render('workspaces/rts/analytics', [
            'workspace' => $workspace->loadMissing([
                'shops' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name'),
                'pages' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name'),
                'pageOwners:id,name'
            ]),
        ]);
    }

    public function groupByOrderItem(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsOrderItemQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15))
        );
    }

    public function groupByPrice(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsPriceQuery($workspace, $request))->get()
        );
    }

    public function groupByDeliveryAttempts(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsDeliveryAttemptsQuery($workspace, $request))->get()
        );
    }

    public function groupByCxRts(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsCxQuery($workspace, $request))
                ->ofType($request->input('type', 'latest'))
                ->get()
        );
    }

    public function groupByConfirmedBy(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsConfirmedByQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15))
        );
    }

    public function groupByRider(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsRiderQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15))
        );
    }

    public function groupByProvinces(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsLocationQuery($workspace, $request))
                ->byProvince()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10))
        );
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        return response()->json(
            (new RtsLocationQuery($workspace, $request))
                ->byCity()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10))
        );
    }
}

<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\RtsCxQuery;
use App\Queries\RtsDeliveryAttemptsQuery;
use App\Queries\RtsLocationQuery;
use App\Queries\RtsOrderItemQuery;
use App\Queries\RtsPriceQuery;
use App\Queries\RtsAdQuery;
use App\Queries\RtsConfirmedByQuery;
use App\Queries\RtsOrderFrequencyQuery;
use App\Queries\RtsRiderQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $key  = $this->cacheKey($workspace, 'order-item', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsOrderItemQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15));
        });

        return response()->json($data);
    }

    public function groupByPrice(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'price', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsPriceQuery($workspace, $request))->get();
        });

        return response()->json($data);
    }

    public function groupByDeliveryAttempts(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'delivery-attempts', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsDeliveryAttemptsQuery($workspace, $request))->get();
        });

        return response()->json($data);
    }

    public function groupByCxRts(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'cx-rts', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsCxQuery($workspace, $request))
                ->ofType($request->input('type', 'latest'))
                ->get();
        });

        return response()->json($data);
    }

    public function groupByAd(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'ad', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsAdQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15));
        });

        return response()->json($data);
    }

    public function groupByConfirmedBy(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'confirmed-by', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsConfirmedByQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15));
        });

        return response()->json($data);
    }

    public function groupByOrderFrequency(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'order-frequency', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsOrderFrequencyQuery($workspace, $request))->get();
        });

        return response()->json($data);
    }

    public function groupByRider(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'rider', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsRiderQuery($workspace, $request))
                ->sort($request->input('sort', '-total_orders'))
                ->get($request->input('per_page', 15));
        });

        return response()->json($data);
    }

    public function groupByProvinces(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'provinces', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsLocationQuery($workspace, $request))
                ->byProvince()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10));
        });

        return response()->json($data);
    }

    public function groupByCities(Request $request, Workspace $workspace)
    {
        $key  = $this->cacheKey($workspace, 'cities', $request);
        $data = Cache::remember($key, $this->ttl($request), function () use ($request, $workspace) {
            return (new RtsLocationQuery($workspace, $request))
                ->byCity()
                ->search($request->input('search', ''))
                ->sort($request->input('sort', '-total_orders'))
                ->paginate($request->input('per_page', 10));
        });

        return response()->json($data);
    }

    private function cacheKey(Workspace $workspace, string $group, Request $request): string
    {
        $params = $request->only([
            'start_date', 'end_date', 'sort', 'per_page', 'page',
            'search', 'type', 'filter',
        ]);

        return 'rts:' . $workspace->id . ':' . $group . ':' . md5(json_encode($params));
    }

    /**
     * 24-hour TTL for fully historical ranges; 5 minutes when the range touches today.
     */
    private function ttl(Request $request): int
    {
        $endDate = $request->input('end_date');

        if ($endDate && Carbon::parse($endDate)->startOfDay()->lt(Carbon::today())) {
            return 60 * 60 * 24;
        }

        return 60 * 5;
    }
}

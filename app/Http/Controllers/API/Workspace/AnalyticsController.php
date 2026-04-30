<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Metrics\MetricSource;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);
        $source = $this->source($request);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':'.$this->makeCacheKey($request->only(['date_range', 'filter', 'metric']));

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source) {
            return $workspace->metrics($request->array('date_range', []), $request->array('filter', []), $source)
                ->extract($request->array('metric'));
        });

        return response()->json($data);
    }

    public function breakdown(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);
        $source = $this->source($request);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':breakdown:'.$this->makeCacheKey($request->only(['date_range', 'filter', 'metric', 'group']));

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $request->array('filter', []),
                $source,
            )->breakdown(
                $request->input('metric', 'totalSales'),
                $request->input('group', 'daily')
            );
        });

        return response()->json(['data' => $data]);
    }

    public function perPage(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);
        $source = $this->source($request);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-page:'.$this->makeCacheKey($request->only(['date_range', 'filter', 'metric']));

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $request->array('filter', []),
                $source,
            )->perPage(
                $request->input('metric', 'totalSales')
            );
        });

        return response()->json(['data' => $data]);
    }

    public function perShop(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);
        $source = $this->source($request);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-shop:'.$this->makeCacheKey($request->only(['date_range', 'filter', 'metric']));

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $request->array('filter', []),
                $source,
            )->perShop(
                $request->input('metric', 'totalSales')
            );
        });

        return response()->json(['data' => $data]);
    }

    public function perUser(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);
        $source = $this->source($request);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-user:'.$this->makeCacheKey($request->only(['date_range', 'filter', 'metric']));

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $request->array('filter', []),
                $source,
            )->perUser(
                $request->input('metric', 'totalSales')
            );
        });

        return response()->json(['data' => $data]);
    }

    private function source(Request $request): string
    {
        return MetricSource::normalize($request->input('source'));
    }

    /**
     * Normalize filter arrays before hashing to prevent cache fragmentation
     * when the same values arrive in different orders.
     */
    private function makeCacheKey(array $data): string
    {
        if (isset($data['filter']) && is_array($data['filter'])) {
            foreach ($data['filter'] as $k => $v) {
                if (is_array($v)) {
                    sort($data['filter'][$k]);
                }
            }
        }

        if (isset($data['metric']) && is_array($data['metric'])) {
            sort($data['metric']);
        }

        return md5(json_encode($data));
    }

    /**
     * Use a 24-hour TTL for fully historical ranges (end_date is before today),
     * and 5 minutes for ranges that include today or the future.
     */
    private function ttl(array $dateRange): int
    {
        return 1;
        $endDate = $dateRange['end_date'] ?? null;

        if ($endDate && Carbon::parse($endDate)->startOfDay()->lt(Carbon::today())) {
            return 60 * 60 * 24;
        }

        return 60 * 5;
    }
}

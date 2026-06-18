<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Metrics\MetricSource;
use App\Models\Page;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);
        $source = $this->source($request);
        $filter = $this->scopedFilter($request, $workspace);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':'.$this->makeCacheKey([
            'date_range' => $request->array('date_range', []),
            'filter' => $filter,
            'metric' => $request->array('metric'),
        ]);

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source, $filter) {
            return $workspace->metrics($request->array('date_range', []), $filter, $source)
                ->extract($request->array('metric'));
        });

        return response()->json($data);
    }

    public function breakdown(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);
        $source = $this->source($request);
        $filter = $this->scopedFilter($request, $workspace);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':breakdown:'.$this->makeCacheKey([
            'date_range' => $request->array('date_range', []),
            'filter' => $filter,
            'metric' => $request->array('metric'),
            'group' => $request->input('group'),
        ]);

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source, $filter) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $filter,
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
        $filter = $this->scopedFilter($request, $workspace);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-page:'.$this->makeCacheKey([
            'date_range' => $request->array('date_range', []),
            'filter' => $filter,
            'metric' => $request->array('metric'),
        ]);

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source, $filter) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $filter,
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
        $filter = $this->scopedFilter($request, $workspace);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-shop:'.$this->makeCacheKey([
            'date_range' => $request->array('date_range', []),
            'filter' => $filter,
            'metric' => $request->array('metric'),
        ]);

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source, $filter) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $filter,
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
        $filter = $this->scopedFilter($request, $workspace);

        $cacheKey = 'analytics:'.$workspace->id.':'.$source.':per-user:'.$this->makeCacheKey([
            'date_range' => $request->array('date_range', []),
            'filter' => $filter,
            'metric' => $request->array('metric'),
        ]);

        $data = Cache::remember($cacheKey, $this->ttl($request->array('date_range', [])), function () use ($request, $workspace, $source, $filter) {
            return $workspace->metrics(
                $request->array('date_range', []),
                $filter,
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
     * Constrain the metric filter to the pages a scoped user may see. Unrestricted
     * users (owner / super-admin / "View All Workspace Data") keep the filter as-is.
     * The resolved filter is also what feeds the cache key, so cached results never
     * leak across users with different visibility.
     */
    private function scopedFilter(Request $request, Workspace $workspace): array
    {
        $filter = $request->array('filter', []);
        $user = $request->user();

        if (! $user || ! TeamVisibility::shouldScope($user, $workspace)) {
            return $filter;
        }

        $visiblePageIds = Page::where('workspace_id', $workspace->id)
            ->visibleTo($user, $workspace)
            ->pluck('id')
            ->all();

        if (! empty($filter['page_ids'])) {
            $requested = is_array($filter['page_ids'])
                ? $filter['page_ids']
                : explode(',', (string) $filter['page_ids']);

            $visiblePageIds = array_values(array_intersect(
                array_map('intval', $requested),
                array_map('intval', $visiblePageIds),
            ));
        }

        // Fail-closed: an empty visible set must match no pages, not "all pages".
        $filter['page_ids'] = empty($visiblePageIds) ? [-1] : $visiblePageIds;

        return $filter;
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

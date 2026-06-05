<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Metrics\MetricSource;
use App\Models\Workspace;
use App\Support\TeamScope;
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
            'metric' => $request->input('metric'),
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
            'metric' => $request->input('metric'),
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
            'metric' => $request->input('metric'),
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
            'metric' => $request->input('metric'),
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
            'metric' => $request->input('metric'),
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

    /**
     * Apply mandatory team scoping to the client-supplied filter.
     *
     * Restricted users (those without "View All Workspace Data") may only see
     * their team's data. Because every metric AND-s pages.owner_id IN (...),
     * forcing user_ids to the allowed owners means no client filter can broaden
     * the result beyond the team — it can only narrow it. team_ids is also set
     * as defense-in-depth for any metric that scopes by team rather than owner.
     */
    private function scopedFilter(Request $request, Workspace $workspace): array
    {
        $filter = $request->array('filter', []);

        $allowedOwners = TeamScope::allowedOwnerIds($request->user(), $workspace);

        if ($allowedOwners === null) {
            return $filter; // unrestricted
        }

        $requested = $this->ids($filter['user_ids'] ?? null);

        // Never fall back to "no filter" (= all data) when the requested users
        // fall outside scope; use a sentinel that matches nothing instead.
        $userIds = $requested === null
            ? $allowedOwners
            : array_values(array_intersect($requested, $allowedOwners));

        $filter['user_ids'] = $userIds ?: [-1];

        $teamIds = TeamScope::allowedTeamIds($request->user(), $workspace);
        if (! empty($teamIds)) {
            $filter['team_ids'] = $teamIds;
        }

        return $filter;
    }

    /**
     * Normalize a filter value (array, CSV string, or null) into a list of ints.
     */
    private function ids(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $list = is_array($value) ? $value : explode(',', (string) $value);
        $list = array_values(array_filter($list, fn ($v) => $v !== null && $v !== ''));
        $list = array_map('intval', $list);

        return $list ?: null;
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

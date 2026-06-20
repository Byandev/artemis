<?php

namespace App\Queries;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\Insight;

class PageRoasTrackerQuery
{
    public const STATUS_OPTIONS = [
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped Out',
        'odz_inc' => 'ODZ/INC',
        'in_transit' => 'In Transit',
        'on_delivery' => 'On Delivery',
        'returned' => 'Returned',
        'delivered' => 'Delivered',
    ];

    /**
     * Mirrors App\Support\Analytics\LiveReader specs so tracker totals tally
     * with dashboard cards, which default to LIVE source.
     */
    private const ORDER_EVENT_STATUSES = [
        'confirmed' => ['date_col' => 'confirmed_at', 'expr' => 'final_amount', 'extra' => 'status NOT IN (6, 7)'],
        'shipped' => ['date_col' => 'shipped_at', 'expr' => 'final_amount', 'extra' => 'status NOT IN (6, 7)'],
        'returned' => ['date_col' => 'returned_at', 'expr' => 'final_amount', 'extra' => null],
        'delivered' => ['date_col' => 'delivered_at', 'expr' => 'final_amount', 'extra' => null],
    ];

    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $user,
        private readonly Request $request,
    ) {}

    public function payload(): array
    {
        [$startDate, $endDate] = $this->dateRange();
        $selectedStatuses = $this->selectedStatuses();
        $pages = $this->filteredPages();
        $pageIds = $pages->pluck('id')->all();
        $dates = $this->dates($startDate, $endDate);

        $metrics = $this->metrics($pageIds, $startDate, $endDate, $selectedStatuses);
        $adSpend = $this->adSpend($pageIds, $startDate, $endDate);
        $tracker = $this->buildTracker($pages, $dates, $metrics, $adSpend);

        return [
            'filters' => [
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'shopId' => $this->request->input('shop_id'),
                'pageId' => $this->request->input('page_id'),
                'pageStatus' => $this->request->input('page_status', 'all'),
                'search' => $this->request->input('search', ''),
                'statuses' => $selectedStatuses,
                'showCpp' => $this->request->boolean('show_cpp'),
            ],
            'statusOptions' => collect(self::STATUS_OPTIONS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'shops' => $this->shops(),
            'pages' => $this->pages(),
            'showCpp' => $this->request->boolean('show_cpp'),
            ...$tracker,
        ];
    }

    private function dateRange(): array
    {
        $defaultEnd = now()->subDay()->toDateString();
        $defaultStart = now()->startOfMonth()->toDateString();

        $start = Carbon::parse($this->request->input('start_date', $defaultStart))->startOfDay();
        $end = Carbon::parse($this->request->input('end_date', $defaultEnd))->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 62) {
            $end = $start->copy()->addDays(62);
        }

        return [$start, $end];
    }

    private function selectedStatuses(): array
    {
        $statuses = $this->request->input('statuses', ['confirmed']);
        $statuses = is_array($statuses) ? $statuses : explode(',', (string) $statuses);

        $valid = array_values(array_intersect($statuses, array_keys(self::STATUS_OPTIONS)));

        return $valid ?: ['confirmed'];
    }

    private function filteredPages(): Collection
    {
        return Page::query()
            ->where('workspace_id', $this->workspace->id)
            ->visibleTo($this->user, $this->workspace)
            ->with(['shop:id,name'])
            ->when($this->request->filled('shop_id'), fn ($q) => $q->where('shop_id', $this->request->integer('shop_id')))
            ->when($this->request->filled('page_id'), fn ($q) => $q->where('id', $this->request->integer('page_id')))
            ->when(
                in_array($this->request->input('page_status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('status', $this->request->input('page_status')),
            )
            ->when($this->request->filled('search'), function ($q) {
                $q->where('name', 'like', '%'.$this->request->string('search')->trim().'%');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'shop_id', 'status']);
    }

    private function shops(): Collection
    {
        return Shop::query()
            ->where('workspace_id', $this->workspace->id)
            ->when(
                TeamVisibility::shouldScope($this->user, $this->workspace),
                fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($this->user, $this->workspace)),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function pages(): Collection
    {
        return Page::query()
            ->where('workspace_id', $this->workspace->id)
            ->visibleTo($this->user, $this->workspace)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function metrics(array $pageIds, Carbon $startDate, Carbon $endDate, array $statuses): array
    {
        if ($pageIds === []) {
            return [];
        }

        $metrics = $this->orderEventMetrics($pageIds, $startDate, $endDate, $statuses);
        $operationalMetrics = $this->operationalMetrics($pageIds, $startDate, $endDate, $statuses);

        foreach ($operationalMetrics as $key => $row) {
            if (! isset($metrics[$key])) {
                $metrics[$key] = (object) [
                    'page_id' => $row->page_id,
                    'date' => $row->date,
                    'orders' => 0,
                    'sales' => 0.0,
                ];
            }

            $metrics[$key]->orders += (int) $row->orders;
            $metrics[$key]->sales += (float) $row->sales;
        }

        return $metrics;
    }

    private function orderEventMetrics(array $pageIds, Carbon $startDate, Carbon $endDate, array $statuses): array
    {
        $eventStatuses = array_values(array_intersect($statuses, array_keys(self::ORDER_EVENT_STATUSES)));

        if ($eventStatuses === []) {
            return [];
        }

        $metrics = [];

        foreach ($eventStatuses as $status) {
            foreach ($this->pageIdChunks($pageIds) as $chunk) {
                $rows = $this->orderEventQuery($status, $chunk, $startDate, $endDate)->get();
                $this->mergeMetricRows($metrics, $rows);
            }
        }

        return $metrics;
    }

    private function orderEventQuery(string $status, array $pageIds, Carbon $startDate, Carbon $endDate)
    {
        $spec = self::ORDER_EVENT_STATUSES[$status];
        $dateColumn = $spec['date_col'];

        return DB::table('pancake_orders')
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('page_id', $pageIds)
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [
                $startDate->copy()->startOfDay()->toDateTimeString(),
                $endDate->copy()->endOfDay()->toDateTimeString(),
            ])
            ->when($spec['extra'], fn ($q, string $extra) => $q->whereRaw($extra))
            ->groupBy('page_id', DB::raw("DATE($dateColumn)"))
            ->select('page_id')
            ->selectRaw("DATE($dateColumn) as date")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM('.$spec['expr'].'), 0) as sales');
    }

    private function operationalMetrics(array $pageIds, Carbon $startDate, Carbon $endDate, array $statuses): array
    {
        $operationalStatuses = array_values(array_intersect($statuses, ['odz_inc', 'in_transit', 'on_delivery']));

        if ($operationalStatuses === []) {
            return [];
        }

        $metrics = [];

        foreach ($operationalStatuses as $status) {
            foreach ($this->pageIdChunks($pageIds) as $chunk) {
                $rows = $this->operationalStatusQuery($status, $chunk, $startDate, $endDate)->get();
                $this->mergeMetricRows($metrics, $rows);
            }
        }

        return $metrics;
    }

    private function operationalStatusQuery(string $status, array $pageIds, Carbon $startDate, Carbon $endDate)
    {
        $events = $this->operationalEventsBase($pageIds, $startDate, $endDate);

        $this->applyOperationalStatus($events, $status);

        $dedupedEvents = $events
            ->selectRaw('
                    pod.page_id,
                    pod.delivery_date as date,
                    pod.order_id,
                    MAX(po.final_amount) as final_amount
                ')
            ->groupBy('pod.page_id', 'pod.delivery_date', 'pod.order_id');

        return DB::query()
            ->fromSub($dedupedEvents, 'operational_events')
            ->groupBy('page_id', 'date')
            ->select('page_id', 'date')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(final_amount) as sales');
    }

    private function operationalEventsBase(array $pageIds, Carbon $startDate, Carbon $endDate)
    {
        return DB::table('pancake_order_for_delivery as pod')
            ->join('pancake_orders as po', 'po.id', '=', 'pod.order_id')
            ->where('pod.workspace_id', $this->workspace->id)
            ->whereIn('pod.page_id', $pageIds)
            ->whereBetween('pod.delivery_date', [$startDate->toDateString(), $endDate->toDateString()]);
    }

    private function applyOperationalStatus($query, string $status): void
    {
        match ($status) {
            'odz_inc' => $query->whereIn(DB::raw('UPPER(pod.status)'), ['ODZ', 'INC', 'INCORRECT NUMBER']),
            'in_transit' => $query->where(DB::raw('UPPER(pod.status)'), 'IN TRANSIT'),
            'on_delivery' => $query
                ->where(function ($q) {
                    $q->whereNull('pod.parcel_status')
                        ->orWhereNotIn(DB::raw('LOWER(pod.parcel_status)'), [
                            'delivered',
                            'returned',
                            'returning',
                            'undeliverable',
                            'cancelled',
                        ]);
                })
                ->whereNotIn(DB::raw('UPPER(pod.status)'), [
                    'DELIVERED',
                    'RETURNING',
                    'CANCELLED',
                    'CX CBR',
                    'RIDER CBR',
                ]),
            default => null,
        };
    }

    private function mergeMetricRows(array &$metrics, iterable $rows): void
    {
        foreach ($rows as $row) {
            $key = $row->date.':'.$row->page_id;

            if (! isset($metrics[$key])) {
                $metrics[$key] = (object) [
                    'page_id' => $row->page_id,
                    'date' => $row->date,
                    'orders' => 0,
                    'sales' => 0.0,
                ];
            }

            $metrics[$key]->orders += (int) $row->orders;
            $metrics[$key]->sales += (float) $row->sales;
        }
    }

    /**
     * Keep large workspaces from generating huge IN (...) predicates. Each
     * aggregate query can still use the workspace/date indexes and filter the
     * current page batch cheaply.
     */
    private function pageIdChunks(array $pageIds): array
    {
        return array_chunk($pageIds, 500);
    }

    /**
     * Actual ad spend per page per day, keyed "Y-m-d:page_id", from Meta's
     * reported insights (meta_ads_insights.spend) — not the configured daily
     * budget. Insights are keyed by ad; we roll them up to the page through their
     * ad set's meta_page_id (which equals the local Page id) and SUM per day in
     * the database — one grouped query, no N+1, no loading raw rows into memory.
     *
     * No active_sync/effective_status filter: historical spend is real money and
     * must be counted even if the ad set is now paused or its account has stopped
     * syncing. Workspace scoping is implicit — $pageIds is already workspace- and
     * visibility-filtered.
     */
    private function adSpend(array $pageIds, Carbon $startDate, Carbon $endDate): array
    {
        if ($pageIds === []) {
            return [];
        }

        return Insight::query()
            ->join('meta_ads_sets as s', 's.id', '=', 'meta_ads_insights.meta_ads_set_id')
            ->whereIn('s.meta_page_id', $pageIds)
            ->whereBetween('meta_ads_insights.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('s.meta_page_id', 'meta_ads_insights.date')
            ->select('s.meta_page_id as page_id', 'meta_ads_insights.date as date')
            ->selectRaw('SUM(meta_ads_insights.spend) as ad_spent')
            ->get()
            ->keyBy(fn (Insight $row) => $row->date->toDateString().':'.$row->page_id)
            ->all();
    }

    private function dates(Carbon $startDate, Carbon $endDate): array
    {
        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->all();
    }

    private function buildTracker(Collection $pages, array $dates, array $metrics, array $adSpend): array
    {
        $totals = [];

        foreach ($pages as $page) {
            $totals[$page->id] = ['orders' => 0, 'sales' => 0.0, 'adSpent' => 0.0];
        }

        $rows = collect($dates)->map(function (string $date) use ($pages, $metrics, $adSpend, &$totals) {
            $cells = [];

            foreach ($pages as $page) {
                $key = $date.':'.$page->id;
                $orders = (int) ($metrics[$key]->orders ?? 0);
                $sales = (float) ($metrics[$key]->sales ?? 0);
                $adSpent = (float) ($adSpend[$key]->ad_spent ?? 0);
                $cell = $this->cell($orders, $sales, $adSpent);

                $cells[(string) $page->id] = $cell;
                $totals[$page->id]['orders'] += $orders;
                $totals[$page->id]['sales'] += $sales;
                $totals[$page->id]['adSpent'] += $adSpent;
            }

            return ['date' => $date, 'cells' => $cells];
        })->all();

        $totalCells = [];
        $averageCells = [];
        $dayCount = max(count($dates), 1);

        foreach ($pages as $page) {
            $totalCells[(string) $page->id] = $this->cell(
                $totals[$page->id]['orders'],
                $totals[$page->id]['sales'],
                $totals[$page->id]['adSpent'],
            );
            $averageCells[(string) $page->id] = $this->cell(
                (int) round($totals[$page->id]['orders'] / $dayCount),
                $totals[$page->id]['sales'] / $dayCount,
                $totals[$page->id]['adSpent'] / $dayCount,
            );
        }

        return [
            'pageColumns' => $pages->map(fn ($page) => [
                'id' => (string) $page->id,
                'name' => $page->name,
                'shopName' => $page->shop?->name,
                'status' => $page->status,
            ])->values(),
            'rows' => $rows,
            'summaryRows' => [
                ['label' => 'Total Amount', 'kind' => 'total', 'cells' => $totalCells],
                ['label' => 'Average', 'kind' => 'average', 'cells' => $averageCells],
            ],
            'analysis' => $this->analysis($pages, $totalCells),
        ];
    }

    private function analysis(Collection $pages, array $totalCells): array
    {
        $pageLookup = $pages->keyBy(fn ($page) => (string) $page->id);
        $cells = collect($totalCells);
        $totalOrders = (int) $cells->sum('orders');
        $totalSales = (float) $cells->sum('sales');
        $totalAdSpent = (float) $cells->sum('adSpent');

        $withSpend = $cells->filter(fn ($cell) => (float) $cell['adSpent'] > 0);
        $best = $withSpend->sortByDesc('roas')->keys()->first();
        $worst = $withSpend->sortBy('roas')->keys()->first();
        $topSpend = $cells->sortByDesc('adSpent')->keys()->first();

        return [
            'totalOrders' => $totalOrders,
            'totalSales' => round($totalSales, 2),
            'totalAdSpent' => round($totalAdSpent, 2),
            'roas' => $totalAdSpent > 0 ? round($totalSales / $totalAdSpent, 2) : 0.0,
            'cpp' => $totalOrders > 0 ? round($totalAdSpent / $totalOrders, 2) : 0.0,
            'activePageCount' => $cells->filter(fn ($cell) => (int) $cell['orders'] > 0 || (float) $cell['adSpent'] > 0)->count(),
            'bestPage' => $this->pageInsight($best, $pageLookup, $totalCells),
            'worstPage' => $this->pageInsight($worst, $pageLookup, $totalCells),
            'topSpendPage' => $this->pageInsight($topSpend, $pageLookup, $totalCells),
        ];
    }

    private function pageInsight(?string $pageId, Collection $pageLookup, array $totalCells): ?array
    {
        if (! $pageId || ! isset($totalCells[$pageId]) || ! $pageLookup->has($pageId)) {
            return null;
        }

        return [
            'id' => $pageId,
            'name' => $pageLookup[$pageId]->name,
            ...$totalCells[$pageId],
        ];
    }

    private function cell(int $orders, float $sales, float $adSpent): array
    {
        return [
            'orders' => $orders,
            'sales' => round($sales, 2),
            'adSpent' => round($adSpent, 2),
            'roas' => $adSpent > 0 ? round($sales / $adSpent, 2) : 0.0,
            'cpp' => $orders > 0 ? round($adSpent / $orders, 2) : 0.0,
        ];
    }
}

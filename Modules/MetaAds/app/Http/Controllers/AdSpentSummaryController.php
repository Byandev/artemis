<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PageDailyBudgetRecord;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdSpentSummaryController extends Controller
{
    /**
     * Optional status "views" — each adds an orders/amount/ROAS column group to
     * the table. Conditions are trusted SQL fragments (never user input), keyed by
     * the slug the frontend checkboxes send.
     */
    private const VIEW_CONDITIONS = [
        'shipped_out' => 'pancake_orders.status = 2',
        'odz_inc' => "pancake_orders.parcel_status = 'undeliverable'",
        'in_transit' => "pancake_orders.parcel_status = 'on_the_way'",
        'on_delivery' => "pancake_orders.parcel_status = 'out_for_delivery'",
        'returned' => 'pancake_orders.status IN (4, 5)',
        'delivered' => 'pancake_orders.status = 3',
    ];

    /**
     * Ad Spent ROAS Summary — one row per day over the selected range with total
     * orders, total order amount, total ad spend, and ROAS (amount ÷ spend), plus
     * an optional column group per selected status view.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $user = $request->user();

        // Default to the trailing 7 days (inclusive).
        $end = ($request->date('end_date') ?? now())->startOfDay();
        $start = ($request->date('start_date') ?? now()->copy()->subDays(6))->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $scope = TeamVisibility::shouldScope($user, $workspace);

        // Keep only recognised view slugs, in a stable order.
        $views = collect(array_keys(self::VIEW_CONDITIONS))
            ->intersect((array) $request->input('views', []))
            ->values();

        // Build the grouped SELECT: totals + a conditional pair per selected view.
        $select = [
            DB::raw('DATE(pancake_orders.inserted_at) as day'),
            DB::raw('COUNT(*) as orders'),
            DB::raw('COALESCE(SUM(pancake_orders.total_amount), 0) as amount'),
        ];
        foreach ($views as $view) {
            $cond = self::VIEW_CONDITIONS[$view];
            $select[] = DB::raw("SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) as {$view}_orders");
            $select[] = DB::raw("COALESCE(SUM(CASE WHEN {$cond} THEN pancake_orders.total_amount ELSE 0 END), 0) as {$view}_amount");
        }

        $orders = Order::ofWorkspace($workspace)
            ->when($scope, fn ($q) => $q->visibleTo($user, $workspace))
            ->whereBetween('pancake_orders.inserted_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->groupBy(DB::raw('DATE(pancake_orders.inserted_at)'))
            ->get($select)
            ->keyBy(fn ($r) => (string) $r->day);

        // Ad spend per day from the page daily budget records.
        $spend = PageDailyBudgetRecord::query()
            ->where('workspace_id', $workspace->id)
            ->when($scope, fn ($q) => $q->whereHas('page', fn ($p) => $p->visibleTo($user, $workspace)))
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->get(['date', DB::raw('COALESCE(SUM(budget), 0) as spent')])
            ->keyBy(fn ($r) => substr((string) $r->date, 0, 10));

        // One row per calendar day so gaps in either dataset still render.
        $rows = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $day = $cursor->toDateString();
            $order = $orders[$day] ?? null;
            $amount = (float) ($order->amount ?? 0);
            $adSpent = (float) ($spend[$day]->spent ?? 0);

            $viewsData = [];
            foreach ($views as $view) {
                $vAmount = (float) ($order->{$view.'_amount'} ?? 0);
                $viewsData[$view] = [
                    'orders' => (int) ($order->{$view.'_orders'} ?? 0),
                    'amount' => round($vAmount, 2),
                    'roas' => $adSpent > 0 ? round($vAmount / $adSpent, 2) : null,
                ];
            }

            $rows[] = [
                'date' => $day,
                'total_orders' => (int) ($order->orders ?? 0),
                'total_orders_amount' => round($amount, 2),
                'total_ad_spent' => round($adSpent, 2),
                'roas' => $adSpent > 0 ? round($amount / $adSpent, 2) : null,
                'views' => $viewsData,
            ];
        }

        return Inertia::render('workspaces/integrations/meta-ad-spent-summary', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'views' => $views->all(),
            ],
            'rows' => $rows,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\AdRecord;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of user's workspaces.
     */
    public function index(Request $request)
    {
        $workspaces = $request->user()
            ->workspaces()
            ->with('owner')
            ->withCount('users')
            ->latest()
            ->get();

        return Inertia::render('workspaces/index', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Show the form for creating a new workspace.
     */
    public function create()
    {
        return Inertia::render('workspaces/create');
    }

    /**
     * Store a newly created workspace in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        // Add the user as owner in the pivot table
        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace created successfully.');
    }

    /**
     * Display the specified workspace.
     */
    public function show(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $workspace->load([
            'owner',
            'users' => function ($query) {
                $query->withPivot('role')->latest();
            },
        ]);

        $userRole = $workspace->users()
            ->where('user_id', $request->user()->id)
            ->first()
            ->pivot
            ->role;

        return redirect()->route('workspace.dashboard', $workspace->slug);
    }

    /**
     * Show the form for editing the specified workspace.
     */
    public function edit(Request $request, Workspace $workspace)
    {
        // Only owner and admins can edit workspace
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to edit this workspace.');
        }

        return Inertia::render('workspaces/edit', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update the specified workspace in storage.
     */
    public function update(Request $request, Workspace $workspace)
    {
        // Only owner and admins can update workspace
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to update this workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace->update($validated);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified workspace from storage.
     */
    public function destroy(Request $request, Workspace $workspace)
    {
        // Only the owner can delete the workspace
        if (! $request->user()->ownsWorkspace($workspace)) {
            abort(403, 'Only the workspace owner can delete it.');
        }

        $workspace->delete();

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    /**
     * Switch to a different workspace.
     */
    public function switch(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Store current workspace ID in session
        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('workspace.dashboard', $workspace->slug)
            ->with('success', "Switched to {$workspace->name}.");
    }

    /**
     * Display the workspace dashboard.
     */
    public function dashboard(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Set as current workspace
        session(['current_workspace_id' => $workspace->id]);

        $workspace->load('owner');

        $userRole = $workspace->users()
            ->where('user_id', $request->user()->id)
            ->first()
            ->pivot
            ->role;

        // Get date filters from query
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Get entity filters from query
        $filters = [
            'team_ids' => $request->query('team_ids'),
            'product_ids' => $request->query('product_ids'),
            'page_ids' => $request->query('page_ids'),
            'shop_ids' => $request->query('shop_ids'),
        ];

        // Consolidated query for all order-based stats
        $orderStats = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->applyDateFilter($startDate, $endDate, 'confirmed_at')
            ->applyEntityFilters($filters)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered_orders,
                ROUND(
                    (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                    NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                    2
                ) as rts_rate_percentage
            ')
            ->first();

        // Notification counts (SMS and Chat) - using subquery in a single query
        $notificationStats = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->applyDateFilter($startDate, $endDate, 'confirmed_at')
            ->applyEntityFilters($filters)
            ->selectRaw('
                SUM(CASE WHEN EXISTS(
                    SELECT 1 FROM parcel_journey_notifications pjn
                    WHERE pjn.order_id = orders.id
                    AND pjn.type = "sms"
                    AND pjn.status = "sent"
                ) THEN 1 ELSE 0 END) as sms_sent,
                SUM(CASE WHEN EXISTS(
                    SELECT 1 FROM parcel_journey_notifications pjn
                    WHERE pjn.order_id = orders.id
                    AND pjn.type = "chat"
                    AND pjn.status = "sent"
                ) THEN 1 ELSE 0 END) as chat_msg_sent
            ')
            ->first();

        // Ad spend and sales in one query
        // Note: Ad records don't have direct relationships to pages/products/shops,
        // so they are only filtered by workspace and date range, not by entity filters.
        // This means ad stats represent workspace-wide totals for the selected date range.
        $adStats = AdRecord::ofWorkspace($workspace)
            ->applyDateFilter($startDate, $endDate, 'date')
            ->selectRaw('SUM(spend) as total_ad_spend, SUM(sales) as total_ad_sales')
            ->first();

        $total_ad_spend = $adStats->total_ad_spend ?? 0;
        $total_ad_sales = $adStats->total_ad_sales ?? 0;

        $stats = [
            'total_sales' => $orderStats->total_sales ?? 0,
            'total_orders' => $orderStats->total_orders ?? 0,
            'total_ad_spend' => $total_ad_spend,
            'rts_rate_percentage' => $orderStats->rts_rate_percentage ?? 0,
            'delivered_orders' => $orderStats->delivered_orders ?? 0,
            'sms_sent' => $notificationStats->sms_sent ?? 0,
            'chat_msg_sent' => $notificationStats->chat_msg_sent ?? 0,
            'roas' => $total_ad_spend > 0 ? round($total_ad_sales / $total_ad_spend, 2) : 0.0,
        ];

        // Fetch available filter options
        $availableFilters = [
            'teams' => \App\Models\Team::ofWorkspace($workspace)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'products' => \App\Models\Product::ofWorkspace($workspace)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'pages' => \App\Models\Page::ofWorkspace($workspace)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'shops' => \App\Models\Shop::where('workspace_id', $workspace->id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ];

        return Inertia::render('workspaces/dashboard/index', [
            'workspace' => $workspace,
            'stats' => $stats,
            'filters' => [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'team_ids' => $request->query('team_ids'),
                'product_ids' => $request->query('product_ids'),
                'page_ids' => $request->query('page_ids'),
                'shop_ids' => $request->query('shop_ids'),
            ],
            'availableTeams' => $availableFilters['teams'],
            'availableProducts' => $availableFilters['products'],
            'availablePages' => $availableFilters['pages'],
            'availableShops' => $availableFilters['shops'],
        ]);
    }

    /**
     * Get historical sales vs ad spend data for charts.
     */
    public function getChartData(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Get the number of days to fetch (default: last 30 days)
        $days = $request->query('days', 30);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Get entity filters from query
        $filters = [
            'team_ids' => $request->query('team_ids'),
            'product_ids' => $request->query('product_ids'),
            'page_ids' => $request->query('page_ids'),
            'shop_ids' => $request->query('shop_ids'),
        ];

        // Get sales data by date
        $salesData = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->applyEntityFilters($filters)
            ->applyDateFilter($startDate, $endDate, 'confirmed_at')
            ->selectRaw('DATE(confirmed_at) as date, SUM(total_amount) as total_sales')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_sales', 'date');

        // Get ad spend data by date
        $adSpendData = AdRecord::ofWorkspace($workspace)
            ->applyDateFilter($startDate, $endDate, 'date')
            ->selectRaw('date, SUM(spend) as total_spend')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_spend', 'date');

        // Get RTS data by date using order status (3=delivered, 4,5=returned)
        $rtsData = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->applyEntityFilters($filters)
            ->applyDateFilter($startDate, $endDate, 'confirmed_at')
            ->selectRaw('
                DATE(confirmed_at) as date,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
                SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END) AS total_shipped_count,
                ROUND(
                    (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                    NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                    2
                ) AS rts_rate_percentage
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Merge data and create chart data
        $allDates = collect(array_unique(array_merge(
            $salesData->keys()->toArray(),
            $adSpendData->keys()->toArray(),
            $rtsData->keys()->toArray()
        )))->sort()->values();

        $chartData = $allDates->map(function ($date) use ($salesData, $adSpendData, $rtsData) {
            $sales = $salesData->get($date, 0);
            $spend = $adSpendData->get($date, 0);

            // Get RTS data for this date
            $rtsRecord = $rtsData->get($date);
            $rtsRate = $rtsRecord ? (float) $rtsRecord->rts_rate_percentage : 0.0;

            // Calculate ROAS: Return on Ad Spend = Sales / Spend
            $roas = $spend > 0 ? $sales / $spend : 0;

            return [
                'date' => $date,
                'sales' => (float) $sales,
                'spend' => (float) $spend,
                'roas' => (float) $roas,
                'rts_rate' => $rtsRate,
            ];
        })->values()->all();

        return response()->json([
            'chartData' => $chartData,
        ]);
    }
}

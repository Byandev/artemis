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
        $teamIds = $request->query('team_ids');
        $productIds = $request->query('product_ids');
        $pageIds = $request->query('page_ids');
        $shopIds = $request->query('shop_ids');

        // Build date range condition
        $dateCondition = function ($query, $dateColumn) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                return $query->whereRaw("DATE($dateColumn) >= ? AND DATE($dateColumn) <= ?", [$startDate, $endDate]);
            }

            return $query;
        };
        
        // Build entity filters condition for orders
        $entityFilters = function ($query) use ($teamIds, $productIds, $pageIds, $shopIds) {
            // Filter by page IDs
            if ($pageIds) {
                $query->whereIn('orders.page_id', is_array($pageIds) ? $pageIds : explode(',', $pageIds));
            }
            
            // Filter by shop IDs
            if ($shopIds) {
                $query->whereIn('orders.shop_id', is_array($shopIds) ? $shopIds : explode(',', $shopIds));
            }
            
            // Filter by product IDs (via pages)
            if ($productIds) {
                $query->whereHas('page', function ($q) use ($productIds) {
                    $q->whereIn('product_id', is_array($productIds) ? $productIds : explode(',', $productIds));
                });
            }
            
            // Filter by team IDs (via page owner's teams)
            if ($teamIds) {
                $query->whereHas('page.owner.teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', is_array($teamIds) ? $teamIds : explode(',', $teamIds));
                });
            }
            
            return $query;
        };

        // RTS Stats with date filter
        $rtsStats = Order::selectRaw('
            ROUND(
                (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at');
        $rtsStats = $dateCondition($rtsStats, 'confirmed_at');
        $rtsStats = $entityFilters($rtsStats);
        $rtsStats = $rtsStats->first();

        // Delivered orders with date filter
        $delivered_orders = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('delivered_at')
            ->whereNotNull('confirmed_at');
        $delivered_orders = $dateCondition($delivered_orders, 'confirmed_at');
        $delivered_orders = $entityFilters($delivered_orders);
        $delivered_orders = $delivered_orders->count();

        // SMS sent with date filter
        $sms_sent = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->whereHas('parcelJourneyNotifications', function ($q) {
                $q->where('type', 'sms')
                    ->where('status', 'sent');
            });
        $sms_sent = $dateCondition($sms_sent, 'orders.confirmed_at');
        $sms_sent = $entityFilters($sms_sent);
        $sms_sent = $sms_sent->count();

        // Chat messages sent with date filter
        $chat_msg_sent = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->whereHas('parcelJourneyNotifications', function ($q) {
                $q->where('type', 'chat')
                    ->where('status', 'sent');
            });
        $chat_msg_sent = $dateCondition($chat_msg_sent, 'orders.confirmed_at');
        $chat_msg_sent = $entityFilters($chat_msg_sent);
        $chat_msg_sent = $chat_msg_sent->count();

        // Total ad spend with date filter
        $total_ad_spend = AdRecord::ofWorkspace($workspace);
        $total_ad_spend = $dateCondition($total_ad_spend, 'date');
        $total_ad_spend = $total_ad_spend->sum('spend');

        // Total ad sales with date filter
        $total_ad_sales = AdRecord::ofWorkspace($workspace);
        $total_ad_sales = $dateCondition($total_ad_sales, 'date');
        $total_ad_sales = $total_ad_sales->sum('sales');

        // Total sales with date filter
        $total_sales = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at');
        $total_sales = $dateCondition($total_sales, 'confirmed_at');
        $total_sales = $entityFilters($total_sales);
        $total_sales = $total_sales->sum('total_amount');

        // Total orders with date filter
        $total_orders = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at');
        $total_orders = $dateCondition($total_orders, 'confirmed_at');
        $total_orders = $entityFilters($total_orders);
        $total_orders = $total_orders->count();

        $stats = [
            'total_sales' => $total_sales,
            'total_orders' => $total_orders,
            'total_ad_spend' => $total_ad_spend,
            'rts_rate_percentage' => $rtsStats->rts_rate_percentage ?? 0,
            'delivered_orders' => $delivered_orders,
            'sms_sent' => $sms_sent,
            'chat_msg_sent' => $chat_msg_sent,
        ];

        $stats['roas'] = $total_ad_spend > 0
            ? round(($total_ad_sales / $total_ad_spend))
            : 0.0;

        // Fetch available filter options
        $availableTeams = \App\Models\Team::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
            
        $availableProducts = \App\Models\Product::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
            
        $availablePages = \App\Models\Page::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
            
        $availableShops = \App\Models\Shop::where('workspace_id', $workspace->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

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
            'availableTeams' => $availableTeams,
            'availableProducts' => $availableProducts,
            'availablePages' => $availablePages,
            'availableShops' => $availableShops,
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
        $teamIds = $request->query('team_ids');
        $productIds = $request->query('product_ids');
        $pageIds = $request->query('page_ids');
        $shopIds = $request->query('shop_ids');

        // Build date range condition
        $dateCondition = function ($query, $dateColumn) use ($days, $startDate, $endDate) {
            if ($startDate && $endDate) {
                // Use specific date range
                return $query->whereRaw("DATE($dateColumn) >= ? AND DATE($dateColumn) <= ?", [$startDate, $endDate]);
            } else {
                // Use days offset
                return $query->whereRaw("$dateColumn >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$days]);
            }
        };
        
        // Build entity filters condition for orders
        $entityFilters = function ($query) use ($teamIds, $productIds, $pageIds, $shopIds) {
            // Filter by page IDs
            if ($pageIds) {
                $query->whereIn('orders.page_id', is_array($pageIds) ? $pageIds : explode(',', $pageIds));
            }
            
            // Filter by shop IDs
            if ($shopIds) {
                $query->whereIn('orders.shop_id', is_array($shopIds) ? $shopIds : explode(',', $shopIds));
            }
            
            // Filter by product IDs (via pages)
            if ($productIds) {
                $query->whereHas('page', function ($q) use ($productIds) {
                    $q->whereIn('product_id', is_array($productIds) ? $productIds : explode(',', $productIds));
                });
            }
            
            // Filter by team IDs (via page owner's teams)
            if ($teamIds) {
                $query->whereHas('page.owner.teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', is_array($teamIds) ? $teamIds : explode(',', $teamIds));
                });
            }
            
            return $query;
        };

        // Get sales data by date
        $salesData = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->selectRaw('DATE(confirmed_at) as date, SUM(total_amount) as total_sales');
        $salesData = $dateCondition($salesData, 'confirmed_at');
        $salesData = $entityFilters($salesData);
        $salesData = $salesData->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_sales', 'date');

        // Get ad spend data by date
        $adSpendData = AdRecord::ofWorkspace($workspace)
            ->selectRaw('date, SUM(spend) as total_spend');
        $adSpendData = $dateCondition($adSpendData, 'date');
        $adSpendData = $adSpendData->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_spend', 'date');

        // Get RTS data by date using order status (3=delivered, 4,5=returned)
        $rtsData = Order::where('workspace_id', $workspace->id)
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
            ->whereNotNull('confirmed_at');
        $rtsData = $dateCondition($rtsData, 'confirmed_at');
        $rtsData = $entityFilters($rtsData);
        $rtsData = $rtsData->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Merge data and create chart data
        $allDates = array_unique(array_merge(
            $salesData->keys()->toArray(),
            $adSpendData->keys()->toArray(),
            $rtsData->keys()->toArray()
        ));
        sort($allDates);

        $chartData = array_map(function ($date) use ($salesData, $adSpendData, $rtsData) {
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
        }, $allDates);

        return response()->json([
            'chartData' => $chartData,
        ]);
    }
}

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
        if (!$request->user()->isMemberOf($workspace)) {
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
        if (!$request->user()->isAdminOf($workspace)) {
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
        if (!$request->user()->isAdminOf($workspace)) {
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
        if (!$request->user()->ownsWorkspace($workspace)) {
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
        if (!$request->user()->isMemberOf($workspace)) {
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
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        return Inertia::render('workspaces/dashboard/index', [
            'workspace' => $workspace->loadMissing([
                'shops' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pages' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
            ]),
        ]);
    }

    /**
     * Get historical sales vs ad spend data for charts.
     */
    public function getChartData(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Get the number of days to fetch (default: last 30 days)
        $days = $request->query('days', 30);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // If no date range provided, default to last N days
        if (!$startDate && !$endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays($days)->format('Y-m-d');
        }

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
            ->applyEntityFilters($filters)
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
            $roas = $spend > 0 ? round($sales / $spend, 2) : 0;

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

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

        $rtsStats = Order::selectRaw('
            ROUND(
                (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                2
            ) AS rts_rate_percentage
        ')
            ->where('workspace_id', $workspace->id)
            ->first();

        $delivered_orders = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('delivered_at')
            ->count();

        $sms_sent = Order::where('workspace_id', $workspace->id)
            ->whereHas('parcelJourneyNotifications', function ($q) {
                $q->where('type', 'sms')
                    ->where('status', 'sent');
            })
            ->count();

        $chat_msg_sent = Order::where('workspace_id', $workspace->id)
            ->whereHas('parcelJourneyNotifications', function ($q) {
                $q->where('type', 'chat')
                    ->where('status', 'sent');
            })
            ->count();

        $total_ad_spend = AdRecord::ofWorkspace($workspace)
            ->sum('spend');

        $total_ad_sales = AdRecord::ofWorkspace($workspace)
            ->sum('sales');

        $stats = [
            'total_sales' => Order::where('workspace_id', $workspace->id)
                ->whereNotNull('confirmed_at')
                ->sum('total_amount'),

            'total_orders' => Order::where('workspace_id', $workspace->id)
                ->whereNotNull('confirmed_at')
                ->count(),

            'total_ad_spend' => $total_ad_spend,

            'rts_rate_percentage' => $rtsStats->rts_rate_percentage,

            'delivered_orders' => $delivered_orders,

            'sms_sent' => $sms_sent,

            'chat_msg_sent' => $chat_msg_sent,

        ];

        $stats['roas'] = $total_ad_spend > 0
            ? round(($total_ad_sales / $total_ad_spend))
            : 0.0;

        return Inertia::render('workspaces/dashboard/index', [
            'workspace' => $workspace,
            'stats' => $stats,
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

        // Get sales data by date
        $salesData = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->selectRaw('DATE(confirmed_at) as date, SUM(total_amount) as total_sales')
            ->whereRaw('confirmed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', [$days])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_sales', 'date');

        // Get ad spend data by date
        $adSpendData = AdRecord::ofWorkspace($workspace)
            ->selectRaw('date, SUM(spend) as total_spend')
            ->whereRaw('date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', [$days])
            ->groupBy('date')
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
            ->whereNotNull('confirmed_at')
            ->whereRaw('confirmed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', [$days])
            ->groupBy('date')
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

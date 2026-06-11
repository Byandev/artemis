<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use App\Services\PostHogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    use AuthorizesRequests;

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

    public function create(Request $request)
    {
        abort_unless($this->canCreateWorkspace($request), 403, 'Only workspace admins can create workspaces.');

        return Inertia::render('workspaces/create');
    }

    public function store(Request $request)
    {
        abort_unless($this->canCreateWorkspace($request), 403, 'Only workspace admins can create workspaces.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        (new PostHogService)->capture((string) $request->user()->id, 'workspace_additional_created', [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
        ]);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace created successfully.');
    }

    public function show(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        return redirect()->route('workspace.dashboard', $workspace->slug);
    }

    public function edit(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditWorkspaceSettings->value, $workspace);

        return Inertia::render('workspaces/edit', [
            'workspace' => $workspace,
        ]);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditWorkspaceSettings->value, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace->update($validated);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace updated successfully.');
    }

    public function updatePublicPassword(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditWorkspaceSettings->value, $workspace);

        $validated = $request->validate([
            // Empty/null clears the password (public pages become open again).
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $workspace->update([
            'public_password' => filled($validated['password'] ?? null)
                ? Hash::make($validated['password'])
                : null,
        ]);

        return back()->with(
            'success',
            filled($validated['password'] ?? null)
                ? 'Public pages password set.'
                : 'Public pages password removed.',
        );
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        if (! $request->user()->ownsWorkspace($workspace)) {
            abort(403, 'Only the workspace owner can delete it.');
        }

        $workspace->delete();

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    public function switch(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        session(['current_workspace_id' => $workspace->id]);

        if ($workspace->csr_module_enabled && $request->user()->isCsrOf($workspace)) {
            return redirect()->route('workspaces.csr.dashboard', $workspace->slug)
                ->with('success', "Switched to {$workspace->name}.");
        }

        return redirect()->route('workspace.dashboard', $workspace->slug)
            ->with('success', "Switched to {$workspace->name}.");
    }

    public function dashboard(Request $request, Workspace $workspace)
    {
        if ($request->user()->role === 'admin') {
            return redirect()->route('workspaces.admin.dashboard', $workspace->slug);
        }

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Route the user to the highest-priority dashboard they can access
        // (Main → Sales & Marketing → Video Editor → CSR); if they can access
        // none of them, send them to their profile settings.
        $target = $request->user()->defaultDashboardRouteName($workspace);

        if ($target === null) {
            return redirect()->route('profile.edit', $workspace->slug);
        }

        if ($target !== 'workspace.dashboard') {
            return redirect()->route($target, $workspace->slug);
        }

        return Inertia::render('workspaces/dashboard/index', [
            'workspace' => $workspace->loadMissing([
                'shops' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pages' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'teams' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pageOwners:id,name',
            ]),
            'metricSettings' => [
                'allowed' => $workspace->allowedMetrics(),
                'defaults' => $workspace->defaultMetrics(),
            ],
        ]);
    }

    public function getChartData(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewRtsAnalytics->value, $workspace);

        $days = $request->query('days', 30);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (! $startDate && ! $endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays($days)->format('Y-m-d');
        }

        $filters = [
            'team_ids' => $request->query('team_ids'),
            'product_ids' => $request->query('product_ids'),
            'page_ids' => $request->query('page_ids'),
            'shop_ids' => $request->query('shop_ids'),
        ];

        $salesData = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_at')
            ->applyEntityFilters($filters)
            ->applyDateFilter($startDate, $endDate, 'confirmed_at')
            ->selectRaw('DATE(confirmed_at) as date, SUM(total_amount) as total_sales')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total_sales', 'date');

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

        $allDates = collect(array_unique(array_merge(
            $salesData->keys()->toArray(),
            $rtsData->keys()->toArray()
        )))->sort()->values();

        $chartData = $allDates->map(function ($date) use ($salesData, $rtsData) {
            $sales = $salesData->get($date, 0);
            $rtsRecord = $rtsData->get($date);
            $rtsRate = $rtsRecord ? (float) $rtsRecord->rts_rate_percentage : 0.0;

            return [
                'date' => $date,
                'sales' => (float) $sales,
                'spend' => 0.0,
                'roas' => 0.0,
                'rts_rate' => $rtsRate,
            ];
        })->values()->all();

        return response()->json([
            'chartData' => $chartData,
        ]);
    }

    private function canCreateWorkspace(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || ! $user->workspaces()->exists()) {
            return true;
        }

        return DB::table('workspace_user')
            ->leftJoin('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('workspace_user.user_id', $user->id)
            ->where(function ($query) {
                $query
                    ->whereIn('workspace_user.role', ['owner', 'admin'])
                    ->orWhere('roles.name', 'admin');
            })
            ->exists();
    }
}

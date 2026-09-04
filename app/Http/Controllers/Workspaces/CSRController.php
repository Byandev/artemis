<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    use AuthorizesRequests;

    public function dashboard(Request $request, Workspace $workspace)
    {
        abort_unless($workspace->csr_dashboard_module_enabled, 404);

        // Gate like the other workspace dashboards. CSRs are still let through
        // (they're auto-redirected here and own this personal view), while
        // managers/admins need the explicit permission. Owners and super-admins
        // pass via the Gate::before short-circuit.
        if (! $request->user()->isCsrOf($workspace)) {
            $this->authorize(Permission::ViewCsrDashboard->value, $workspace);
        }

        return Inertia::render('workspaces/csr/dashboard', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
        ]);
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrManagement->value, $workspace);

        $employees = QueryBuilder::for(PancakeUser::class)
            ->whereHas('shops', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with('systemUser')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('pancake_users.name', 'like', "%{$value}%")
                            ->orWhere('pancake_users.email', 'like', "%{$value}%")
                            ->orWhere('pancake_users.phone_number', 'like', "%{$value}%")
                            ->orWhere('pancake_users.status', 'like', "%{$value}%")
                            ->orWhereHas('systemUser', function ($q) use ($value) {
                                $q->where('name', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts([
                'name',
                'email',
                'phone_number',
                'created_at',
                'user_id',
                AllowedSort::field('status', 'pancake_users.status'),
            ])
            ->defaultSort('pancake_users.name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/csr/index', [
            'workspace' => $workspace,
            'employees' => $employees,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'systemUsers' => User::whereHas('workspaces', fn ($query) => $query->where('workspace_id', $workspace->id))->get(),
        ]);
    }

    /**
     * The range and report type the analytics page is showing.
     *
     * Shared by the page and the stats endpoint so the cards and the table can
     * never be reading different days.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function analyticsPeriod(Request $request): array
    {
        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $type = strtolower((string) $request->input('type', 'pos')) === 'erp' ? 'erp' : 'pos';

        return [$from, $to, $type];
    }

    /**
     * Which CSR comparison tab the page opens on.
     *
     * Kept in the URL rather than the browser so a reload, a shared link and
     * the back button all land on the metric that was being read. Anything
     * that isn't one of the endpoint's four keys falls back to sales.
     */
    private function comparisonTab(Request $request): string
    {
        $tab = (string) $request->input('comparison', 'sales');

        return in_array($tab, ['sales', 'rts', 'rmo_called', 'call_time'], true) ? $tab : 'sales';
    }

    public function analytics(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to, $type] = $this->analyticsPeriod($request);

        $isErp = $type === 'erp';
        $drClass = $isErp ? PancakeUserErpDailyReport::class : PancakeUserPosDailyReport::class;

        // Per-CSR sales/delivery rollup for the selected period (POS or ERP).
        $drSummary = $drClass::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_orders)   as total_orders,
                SUM(total_sales)    as total_sales,
                SUM(delivered)      as total_delivered,
                SUM(`returning`)    as total_returning
            ');

        // RMO calling activity is tracked separately from the sales reports.
        // The call report, summed back over the shops it splits a CSR's day into.
        // Aliased to the names the table's columns already sort on: RMO Assigned
        // is now every delivery handed over, PENDING included, where it used to
        // count only the ones that had moved off it.
        $rmoSummary = PancakeUserDailyCallReport::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_rmo_assigned_count)  as total_called,
                SUM(total_rmo_call_time)       as total_call_time,
                SUM(total_rmo_called)          as total_rmo_call_attempts,
                SUM(total_rmo_confirmed_count) as total_confirmed
            ');

        $base = PancakeUser::query()
            ->whereHas('shops', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            });

        $records = QueryBuilder::for($base)
            ->leftJoinSub($drSummary, 'dr', 'dr.pancake_user_id', '=', 'pancake_users.id')
            ->leftJoinSub($rmoSummary, 'rmo', 'rmo.pancake_user_id', '=', 'pancake_users.id')
            ->select('pancake_users.*')
            ->selectRaw('COALESCE(dr.total_orders, 0)              as total_orders')
            ->selectRaw('COALESCE(dr.total_sales, 0)               as total_sales')
            ->selectRaw('COALESCE(dr.total_delivered, 0)           as total_delivered')
            ->selectRaw('COALESCE(dr.total_returning, 0)           as total_returning')
            ->selectRaw('COALESCE(rmo.total_called, 0)             as total_called')
            ->selectRaw('COALESCE(rmo.total_call_time, 0)          as total_call_time')
            ->selectRaw('COALESCE(rmo.total_rmo_call_attempts, 0)  as total_rmo_call_attempts')
            ->selectRaw('COALESCE(rmo.total_confirmed, 0)          as total_confirmed')
            ->selectRaw('
                CASE
                    WHEN (COALESCE(dr.total_returning, 0) + COALESCE(dr.total_delivered, 0)) > 0
                    THEN ROUND(
                        COALESCE(dr.total_returning, 0)
                        / (COALESCE(dr.total_returning, 0) + COALESCE(dr.total_delivered, 0))
                        * 100, 2
                    )
                    ELSE 0
                END as rts_rate
            ')
            // RMO % = RMO assigned over RMO confirmed. Mirrors the frontend
            // computation so the column is sortable server-side.
            ->selectRaw('
                CASE
                    WHEN COALESCE(rmo.total_confirmed, 0) > 0
                    THEN ROUND(
                        COALESCE(rmo.total_called, 0)
                        / COALESCE(rmo.total_confirmed, 0)
                        * 100, 2
                    )
                    ELSE 0
                END as rmo_percentage
            ')
            ->allowedSorts([
                AllowedSort::field('name', 'pancake_users.name'),
                'total_orders',
                'total_sales',
                'total_delivered',
                'total_returning',
                'total_called',
                'total_call_time',
                'total_rmo_call_attempts',
                'total_confirmed',
                'rts_rate',
                'rmo_percentage',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('pancake_users.name', 'like', "%{$value}%");
                }),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/csr/analytics', [
            'workspace' => $workspace,
            'records' => $records,
            'query' => [
                'from' => $from,
                'to' => $to,
                'type' => $isErp ? 'erp' : 'pos',
                'sort' => $request->input('sort', '-total_sales'),
                'page' => $request->integer('page', 1),
                'per_page' => $request->integer('per_page', 10),
                'search' => data_get($request->input('filter', []), 'search'),
                'comparison' => $this->comparisonTab($request),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, PancakeUser $employee)
    {
        $this->authorize(Permission::EditCsrEmployees->value, $workspace);

        if (! $this->employeeBelongsToWorkspace($employee->id, $workspace)) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:ACTIVE,INACTIVE',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $employee->update($validated);

        return redirect()->back()->with('success', 'Employee updated successfully');
    }

    private function employeeBelongsToWorkspace(string $employeeId, Workspace $workspace): bool
    {
        return PancakeUser::query()
            ->whereKey($employeeId)
            ->whereHas('shops', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->exists();
    }
}

<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CsrSchedule;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    use AuthorizesRequests;

    public function dashboard(Request $request, Workspace $workspace)
    {
        $authUser = $request->user();
        $now = CarbonImmutable::now();

        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))
            : $now->startOfMonth();
        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))
            : $now;

        $today = $to->toDateString();
        $monthStart = $from->toDateString();
        $trendFrom = $from->toDateString();

        // Resolve Pancake accounts for this user in this workspace
        $pancakeAccounts = PancakeUser::where('user_id', $authUser->id)
            ->whereHas('shops', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->get(['id', 'name']);

        $pancakeUserIds = $pancakeAccounts->pluck('id')->all();
        $primaryPancakeId = $pancakeUserIds[0] ?? null;

        $drClass = PancakeUserPosDailyReport::class;

        // --- Section 1: My RMO Stats Today ---
        $row = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $authUser->id)
            ->whereBetween('delivery_date', [$monthStart, $today])
            ->selectRaw("
                COUNT(*) as assigned,
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called,
                SUM(CASE WHEN parcel_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN parcel_status = 'returning' THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        $myTodayStats = [
            'assigned' => (int) ($row->assigned ?? 0),
            'called' => (int) ($row->called ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'returning' => (int) ($row->returning_count ?? 0),
            'pending' => (int) ($row->pending ?? 0),
        ];

        // --- Section 2: My Pending Orders (top 5) ---
        $pendingOrders = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $authUser->id)
            ->whereBetween('delivery_date', [$monthStart, $today])
            ->where('status', 'PENDING')
            ->with([
                'order' => fn ($q) => $q->select('id', 'order_number', 'tracking_code', 'final_amount')
                    ->with(['shippingAddress:id,order_id,full_name']),
            ])
            ->limit(5)
            ->get(['id', 'order_id', 'rider_name', 'status', 'delivery_date']);

        // --- Section 3: My Monthly Performance ---
        $myMonthly = ['total_orders' => 0, 'total_sales' => 0, 'delivered' => 0, 'returning_count' => 0, 'rts_rate' => 0, 'total_called' => 0, 'total_call_time' => 0];
        if ($primaryPancakeId) {
            $dr = $drClass::query()
                ->forWorkspaceRange($workspace->id, $monthStart, $today)
                ->where('pancake_user_id', $primaryPancakeId)
                ->selectRaw('
                    COALESCE(SUM(total_orders), 0) as total_orders,
                    COALESCE(SUM(total_sales), 0) as total_sales,
                    COALESCE(SUM(delivered), 0) as delivered,
                    COALESCE(SUM(`returning`), 0) as returning_count,
                    CASE
                        WHEN COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0) > 0
                        THEN ROUND((COALESCE(SUM(`returning`), 0) / (COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0))) * 100, 2)
                        ELSE 0
                    END as rts_rate
                ')
                ->first();

            $rmo = PancakeUserRmoDailyReport::query()
                ->forWorkspaceRange($workspace->id, $monthStart, $today)
                ->where('pancake_user_id', $primaryPancakeId)
                ->selectRaw('
                    COALESCE(SUM(total_called), 0) as total_called,
                    COALESCE(SUM(total_call_time), 0) as total_call_time
                ')
                ->first();

            $myMonthly = [
                'total_orders' => (int) ($dr->total_orders ?? 0),
                'total_sales' => (float) ($dr->total_sales ?? 0),
                'delivered' => (int) ($dr->delivered ?? 0),
                'returning_count' => (int) ($dr->returning_count ?? 0),
                'rts_rate' => (float) ($dr->rts_rate ?? 0),
                'total_called' => (int) ($rmo->total_called ?? 0),
                'total_call_time' => (int) ($rmo->total_call_time ?? 0),
            ];
        }

        // --- Section 4: Team Leaderboard (top 10 this month) ---
        $topCsrs = PancakeUser::query()
            ->select([
                'pancake_users.id as pancake_user_id',
                'pancake_users.name as csr_name',
            ])
            ->whereHas('shops', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->selectSub(
                $drClass::query()
                    ->forWorkspaceRange($workspace->id, $monthStart, $today)
                    ->whereColumn('pancake_user_id', 'pancake_users.id')
                    ->selectRaw('COALESCE(SUM(total_sales), 0)'),
                'total_sales'
            )
            ->selectSub(
                $drClass::query()
                    ->forWorkspaceRange($workspace->id, $monthStart, $today)
                    ->whereColumn('pancake_user_id', 'pancake_users.id')
                    ->selectRaw('COALESCE(SUM(total_orders), 0)'),
                'total_orders'
            )
            ->selectSub(
                $drClass::query()
                    ->forWorkspaceRange($workspace->id, $monthStart, $today)
                    ->whereColumn('pancake_user_id', 'pancake_users.id')
                    ->selectRaw('COALESCE(SUM(delivered), 0)'),
                'delivered'
            )
            ->selectSub(
                $drClass::query()
                    ->forWorkspaceRange($workspace->id, $monthStart, $today)
                    ->whereColumn('pancake_user_id', 'pancake_users.id')
                    ->selectRaw('COALESCE(SUM(`returning`), 0)'),
                'returning_count'
            )
            ->selectSub(
                $drClass::query()
                    ->forWorkspaceRange($workspace->id, $monthStart, $today)
                    ->whereColumn('pancake_user_id', 'pancake_users.id')
                    ->selectRaw('
                        CASE
                            WHEN COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0) > 0
                            THEN ROUND((COALESCE(SUM(`returning`), 0) / (COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0))) * 100, 2)
                            ELSE 0
                        END
                    '),
                'rts_rate'
            )
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        // --- Section 6: 14-Day Daily Trend (my performance over time) ---
        $dailyTrend = [];
        if ($primaryPancakeId) {
            $posRows = $drClass::query()
                ->forWorkspaceRange($workspace->id, $trendFrom, $today)
                ->where('pancake_user_id', $primaryPancakeId)
                ->select(['date', 'total_orders', 'total_sales', 'delivered', 'returning', 'rts_rate'])
                ->orderBy('date')
                ->get()
                ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

            $rmoRows = PancakeUserRmoDailyReport::query()
                ->forWorkspaceRange($workspace->id, $trendFrom, $today)
                ->where('pancake_user_id', $primaryPancakeId)
                ->select(['date', 'total_called', 'total_call_time'])
                ->orderBy('date')
                ->get()
                ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

            // Fill every day in the 14-day range (zero-fill gaps)
            foreach (CarbonPeriod::create($trendFrom, $today) as $day) {
                $d = $day->format('Y-m-d');
                $pos = $posRows->get($d);
                $rmo = $rmoRows->get($d);

                $dailyTrend[] = [
                    'date' => $d,
                    'orders' => (int) ($pos->total_orders ?? 0),
                    'sales' => (float) ($pos->total_sales ?? 0),
                    'delivered' => (int) ($pos->delivered ?? 0),
                    'returning' => (int) ($pos->returning ?? 0),
                    'rts_rate' => (float) ($pos->rts_rate ?? 0),
                    'called' => (int) ($rmo->total_called ?? 0),
                    'call_time' => (int) ($rmo->total_call_time ?? 0),
                ];
            }
        }

        // --- Section 7: Today's Order Status Breakdown ---
        $statusBreakdown = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $authUser->id)
            ->whereBetween('delivery_date', [$monthStart, $today])
            ->groupBy('status')
            ->select(['status', DB::raw('COUNT(*) as count')])
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count])
            ->all();

        // --- Section 8: Team Average (this month, for comparison) ---
        $teamAvg = ['total_orders' => 0, 'total_sales' => 0, 'delivered' => 0, 'returning_count' => 0, 'rts_rate' => 0, 'total_called' => 0, 'total_call_time' => 0];
        $teamCsrCount = PancakeUser::whereHas('shops', fn ($q) => $q->where('workspace_id', $workspace->id))->count();

        if ($teamCsrCount > 0) {
            $teamDr = $drClass::query()
                ->forWorkspaceRange($workspace->id, $monthStart, $today)
                ->whereIn('pancake_user_id', function ($q) use ($workspace) {
                    $q->select('pancake_shop_users.user_id')
                        ->from('pancake_shop_users')
                        ->join('shops', 'pancake_shop_users.shop_id', '=', 'shops.id')
                        ->where('shops.workspace_id', $workspace->id);
                })
                ->selectRaw('
                    COALESCE(SUM(total_orders), 0) as total_orders,
                    COALESCE(SUM(total_sales), 0) as total_sales,
                    COALESCE(SUM(delivered), 0) as delivered,
                    COALESCE(SUM(`returning`), 0) as returning_count
                ')
                ->first();

            $teamRmo = PancakeUserRmoDailyReport::query()
                ->forWorkspaceRange($workspace->id, $monthStart, $today)
                ->whereIn('pancake_user_id', function ($q) use ($workspace) {
                    $q->select('pancake_shop_users.user_id')
                        ->from('pancake_shop_users')
                        ->join('shops', 'pancake_shop_users.shop_id', '=', 'shops.id')
                        ->where('shops.workspace_id', $workspace->id);
                })
                ->selectRaw('
                    COALESCE(SUM(total_called), 0) as total_called,
                    COALESCE(SUM(total_call_time), 0) as total_call_time
                ')
                ->first();

            $totalDel = (int) ($teamDr->delivered ?? 0);
            $totalRet = (int) ($teamDr->returning_count ?? 0);

            $teamAvg = [
                'total_orders' => round((int) ($teamDr->total_orders ?? 0) / $teamCsrCount),
                'total_sales' => round((float) ($teamDr->total_sales ?? 0) / $teamCsrCount, 2),
                'delivered' => round($totalDel / $teamCsrCount),
                'returning_count' => round($totalRet / $teamCsrCount),
                'rts_rate' => ($totalDel + $totalRet) > 0
                    ? round(($totalRet / ($totalDel + $totalRet)) * 100, 2)
                    : 0,
                'total_called' => round((int) ($teamRmo->total_called ?? 0) / $teamCsrCount),
                'total_call_time' => round((int) ($teamRmo->total_call_time ?? 0) / $teamCsrCount),
            ];
        }

        // --- CSR Schedules for the date range ---
        $csrSchedules = CsrSchedule::where('workspace_id', $workspace->id)
            ->whereBetween('date', [$monthStart, $today])
            ->with('pancakeUser:id,name')
            ->orderBy('date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'pancake_user_id' => $s->pancake_user_id,
                'name' => $s->pancakeUser?->name ?? 'Unknown',
                'date' => $s->date->format('Y-m-d'),
                'shift_start' => $s->shift_start?->format('H:i'),
                'shift_end' => $s->shift_end?->format('H:i'),
                'notes' => $s->notes,
            ]);

        return Inertia::render('workspaces/csr/dashboard', [
            'workspace' => $workspace,
            'pancakeAccounts' => $pancakeAccounts,
            'myTodayStats' => $myTodayStats,
            'pendingOrders' => $pendingOrders,
            'myMonthly' => $myMonthly,
            'topCsrs' => $topCsrs,
            'today' => $now->toDateString(),
            'from' => $monthStart,
            'to' => $today,
            'monthLabel' => $from->format('M j').' – '.$to->format('M j, Y'),
            'dailyTrend' => $dailyTrend,
            'statusBreakdown' => $statusBreakdown,
            'teamAvg' => $teamAvg,
            'csrSchedules' => $csrSchedules,
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
                            ->orWhere('pancake_users.status', 'like', "%{$value}%");
                    })->orWhereHas('systemUser', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
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

    public function analytics(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $type = strtolower((string) $request->input('type', 'pos'));
        $drClass = $type === 'erp' ? PancakeUserErpDailyReport::class : PancakeUserPosDailyReport::class;

        $drSub = fn () => $drClass::query()
            ->forWorkspaceRange($workspace->id, $from, $to)
            ->whereColumn('pancake_user_id', 'pancake_users.id');

        $rmoSub = fn () => PancakeUserRmoDailyReport::query()
            ->forWorkspaceRange($workspace->id, $from, $to)
            ->whereColumn('pancake_user_id', 'pancake_users.id');

        $query = PancakeUser::query()
            ->select([
                'pancake_users.id as pancake_user_id',
                'pancake_users.name as csr_name',
            ])
            ->whereHas('shops', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(total_orders), 0)'), 'total_orders')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(total_sales), 0)'), 'total_sales')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(delivered), 0)'), 'delivered')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(`returning`), 0)'), 'returning_count')
            ->selectSub($rmoSub()->selectRaw('COALESCE(SUM(total_called), 0)'), 'total_called')
            ->selectSub($rmoSub()->selectRaw('COALESCE(SUM(total_call_time), 0)'), 'total_call_time')
            ->selectSub(
                $drSub()->selectRaw('
                    CASE
                        WHEN COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0) > 0
                        THEN ROUND((COALESCE(SUM(`returning`), 0) / (COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0))) * 100, 2)
                        ELSE 0
                    END
                '),
                'rts_rate'
            );

        $records = QueryBuilder::for($query)
            ->allowedSorts([
                AllowedSort::field('csr_name'),
                AllowedSort::field('total_orders'),
                AllowedSort::field('total_sales'),
                AllowedSort::field('delivered'),
                AllowedSort::field('returning_count'),
                AllowedSort::field('rts_rate'),
                AllowedSort::field('total_called'),
                AllowedSort::field('total_call_time'),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/csr/analytics', [
            'workspace' => $workspace,
            'records' => $records,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
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

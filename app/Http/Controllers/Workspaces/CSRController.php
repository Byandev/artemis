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

        // --- Current user's pancake accounts with reports eagerly loaded ---
        $pancakeAccounts = PancakeUser::where('user_id', $authUser->id)
            ->whereHas('shops', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with([
                'posReports' => fn ($q) => $q->forWorkspaceRange($workspace->id, $monthStart, $today),
                'rmoReports' => fn ($q) => $q->forWorkspaceRange($workspace->id, $monthStart, $today),
            ])
            ->get(['id', 'name', 'email', 'phone_number', 'status', 'fb_id']);

        $pancakeUserIds = $pancakeAccounts->pluck('id')->all();

        // --- All workspace users with their pancake accounts + reports ---
        $workspaceUsers = User::query()
            ->whereHas('pancakeAccounts', fn ($q) =>
                $q->whereHas('shops', fn ($q2) => $q2->where('workspace_id', $workspace->id))
            )
            ->with(['pancakeAccounts' => fn ($q) =>
                $q->whereHas('shops', fn ($q2) => $q2->where('workspace_id', $workspace->id))
                  ->with([
                      'posReports' => fn ($q) => $q->forWorkspaceRange($workspace->id, $monthStart, $today),
                      'rmoReports' => fn ($q) => $q->forWorkspaceRange($workspace->id, $monthStart, $today),
                  ])
            ])
            ->get(['id', 'name']);

        // --- Section 1: My RMO Stats ---
        $myOrders = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $authUser->id)
            ->whereBetween('delivery_date', [$monthStart, $today])
            ->get(['status', 'parcel_status']);

        $myTodayStats = [
            'assigned' => $myOrders->count(),
            'called' => $myOrders->where('status', '!=', 'PENDING')->count(),
            'delivered' => $myOrders->where('parcel_status', 'delivered')->count(),
            'returning' => $myOrders->where('parcel_status', 'returning')->count(),
            'pending' => $myOrders->where('status', 'PENDING')->count(),
        ];

        // --- Section 2: My Pending Orders (top 5) ---
        $pendingOrders = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $authUser->id)
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

        if (! empty($pancakeUserIds)) {
            $myPosReports = $pancakeAccounts->flatMap->posReports;
            $myRmoReports = $pancakeAccounts->flatMap->rmoReports;

            $myDelivered = (int) $myPosReports->sum('delivered');
            $myReturning = (int) $myPosReports->sum('returning');

            $myMonthly = [
                'total_orders' => (int) $myPosReports->sum('total_orders'),
                'total_sales' => (float) $myPosReports->sum('total_sales'),
                'delivered' => $myDelivered,
                'returning_count' => $myReturning,
                'rts_rate' => ($myDelivered + $myReturning) > 0
                    ? round(($myReturning / ($myDelivered + $myReturning)) * 100, 2)
                    : 0,
                'total_called' => (int) $myRmoReports->sum('total_called'),
                'total_call_time' => (int) $myRmoReports->sum('total_call_time'),
            ];
        }

        // --- Section 4: Team Leaderboard (top 10 this month) ---
        $topCsrs = $workspaceUsers->map(function ($user) {
            $posReports = $user->pancakeAccounts->flatMap->posReports;

            $delivered = (int) $posReports->sum('delivered');
            $returning = (int) $posReports->sum('returning');

            return [
                'user_id' => $user->id,
                'csr_name' => $user->name,
                'total_sales' => (float) $posReports->sum('total_sales'),
                'total_orders' => (int) $posReports->sum('total_orders'),
                'delivered' => $delivered,
                'returning_count' => $returning,
                'rts_rate' => ($delivered + $returning) > 0
                    ? round(($returning / ($delivered + $returning)) * 100, 2)
                    : 0,
            ];
        })->sortByDesc('total_sales')->values()->take(10)->all();

        // --- Section 6: Daily Trend ---
        $dailyTrend = [];
        if (! empty($pancakeUserIds)) {
            $posByDate = $pancakeAccounts->flatMap->posReports
                ->groupBy(fn ($r) => $r->date->format('Y-m-d'));
            $rmoByDate = $pancakeAccounts->flatMap->rmoReports
                ->groupBy(fn ($r) => $r->date->format('Y-m-d'));

            foreach (CarbonPeriod::create($trendFrom, $today) as $day) {
                $d = $day->format('Y-m-d');
                $pos = $posByDate->get($d);
                $rmo = $rmoByDate->get($d);

                $del = (int) ($pos?->sum('delivered') ?? 0);
                $ret = (int) ($pos?->sum('returning') ?? 0);

                $dailyTrend[] = [
                    'date' => $d,
                    'orders' => (int) ($pos?->sum('total_orders') ?? 0),
                    'sales' => (float) ($pos?->sum('total_sales') ?? 0),
                    'delivered' => $del,
                    'returning' => $ret,
                    'rts_rate' => ($del + $ret) > 0 ? round(($ret / ($del + $ret)) * 100, 2) : 0,
                    'called' => (int) ($rmo?->sum('total_called') ?? 0),
                    'call_time' => (int) ($rmo?->sum('total_call_time') ?? 0),
                ];
            }
        }

        // --- Section 7: Order Status Breakdown ---
        $statusBreakdown = $myOrders
            ->groupBy('status')
            ->map(fn ($group, $status) => ['status' => $status, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values()
            ->all();

        // --- Section 8: Team Average ---
        $teamAvg = ['total_orders' => 0, 'total_sales' => 0, 'delivered' => 0, 'returning_count' => 0, 'rts_rate' => 0, 'total_called' => 0, 'total_call_time' => 0];
        $teamCsrCount = $workspaceUsers->count();

        if ($teamCsrCount > 0) {
            $allPosReports = $workspaceUsers->flatMap(fn ($u) => $u->pancakeAccounts->flatMap->posReports);
            $allRmoReports = $workspaceUsers->flatMap(fn ($u) => $u->pancakeAccounts->flatMap->rmoReports);

            $totalDel = (int) $allPosReports->sum('delivered');
            $totalRet = (int) $allPosReports->sum('returning');

            $teamAvg = [
                'total_orders' => (int) round($allPosReports->sum('total_orders') / $teamCsrCount),
                'total_sales' => round((float) $allPosReports->sum('total_sales') / $teamCsrCount, 2),
                'delivered' => (int) round($totalDel / $teamCsrCount),
                'returning_count' => (int) round($totalRet / $teamCsrCount),
                'rts_rate' => ($totalDel + $totalRet) > 0
                    ? round(($totalRet / ($totalDel + $totalRet)) * 100, 2)
                    : 0,
                'total_called' => (int) round($allRmoReports->sum('total_called') / $teamCsrCount),
                'total_call_time' => (int) round($allRmoReports->sum('total_call_time') / $teamCsrCount),
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
            'authUserId' => $authUser->id,
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

<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CsrComparisonMetrics;
use App\Support\TeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    use AuthorizesRequests;

    /**
     * The figures that decide whether a CSR belongs in the breakdown at all,
     * qualified by the joined subquery each comes from.
     *
     * A CSR is on the roster of the workspace's shops whether or not they
     * worked in the range being read, and both rollups come in as left joins
     * coalesced to zero — so anyone who did nothing used to fill a row of
     * zeros, pushing the CSRs who did work down the table and onto page two.
     * One figure moving is enough to be listed; none of them moving is not a
     * row. A CSR with no rollup row at all reads NULL here, which is not `<> 0`
     * either, so the same clause covers them.
     *
     * The two rates are deliberately absent: both are computed from amounts
     * already on this list, so a CSR with a rate has the figures behind it too.
     */
    private const BREAKDOWN_FIGURES = [
        'dr.total_orders',
        'dr.total_sales',
        'dr.total_delivered',
        'dr.total_returning',
        'dr.total_delivered_count',
        'dr.total_returning_count',

        'rmo.total_called',
        'rmo.total_call_time',
        'rmo.total_rmo_call_attempts',
        'rmo.total_rmo_orders',
        'rmo.total_confirmed',
        'rmo.total_all_called',
        'rmo.total_all_call_time',
        'rmo.total_rmo_connected_called',
        'rmo.total_rmo_real_called',
        'rmo.longest_rmo_call_time',
        'rmo.total_rmo_customer_called',
        'rmo.total_rmo_customer_call_time',
        'rmo.total_rmo_rider_called',
        'rmo.total_rmo_rider_call_time',
        'rmo.total_verification_called',
        'rmo.total_verification_call_time',
        'rmo.total_verification_real_called',
        'rmo.total_verified_orders',
    ];

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

    /**
     * The pancake logins linked to the signed-in user, in full.
     *
     * The dashboard next door sums exactly these users' rollup rows without
     * ever naming them, so a CSR reading a figure they do not recognise has no
     * way to tell whether a second login is folded in, or whether the one they
     * expected was never linked at all. This page is that answer: who the
     * workspace thinks they are, and which shops each identity works.
     *
     * Gated and scoped like the dashboard it explains — the module toggle, then
     * the CSR's own membership or the dashboard permission, and rows narrowed
     * by the same `user_id` link and workspace-shop test that
     * CsrDashboardController::ownPancakeUserIds() applies.
     */
    public function pancakeUsers(Request $request, Workspace $workspace)
    {
        abort_unless($workspace->csr_dashboard_module_enabled, 404);

        if (! $request->user()->isCsrOf($workspace)) {
            $this->authorize(Permission::ViewCsrDashboard->value, $workspace);
        }

        $pancakeUsers = PancakeUser::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('shopUsers.shop', fn ($query) => $query->where('workspace_id', $workspace->id))
            // Only this workspace's shops: a pancake login can work shops in
            // several workspaces, and the others are not this page's business.
            ->with(['shops' => fn ($query) => $query
                ->where('shops.workspace_id', $workspace->id)
                ->select('shops.id', 'shops.name')
                ->orderBy('shops.name'),
            ])
            ->orderBy('name')
            ->get();

        $lastActive = $this->lastActiveDates($workspace, $pancakeUsers->pluck('id')->all());

        return Inertia::render('workspaces/csr/pancake-users', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'pancakeUsers' => $pancakeUsers->map(fn (PancakeUser $pancakeUser) => [
                'id' => $pancakeUser->id,
                'name' => $pancakeUser->name,
                'email' => $pancakeUser->email,
                'phone_number' => $pancakeUser->phone_number,
                'fb_id' => $pancakeUser->fb_id,
                // Defaulted the way the CSR management table defaults it, so a
                // row synced before the column existed reads as active rather
                // than as an unknown state.
                'status' => $pancakeUser->status ?: 'ACTIVE',
                'shops' => $pancakeUser->shops
                    ->map(fn ($shop) => ['id' => $shop->id, 'name' => $shop->name])
                    ->values(),
                'last_active_on' => $lastActive[$pancakeUser->id] ?? null,
            ])->values(),
        ]);
    }

    /**
     * The last day each account shows up in either nightly rollup.
     *
     * Both tables are read because the two are filled independently: a CSR who
     * only took calls has no POS row, and one whose shop reports sales without
     * call logs has no call row. The later of the two is the day the account
     * was last doing anything this workspace recorded — which is how a CSR
     * tells a live login from one left over from a previous role.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @return array<string, string> Pancake user id => `YYYY-MM-DD`.
     */
    private function lastActiveDates(Workspace $workspace, array $pancakeUserIds): array
    {
        if ($pancakeUserIds === []) {
            return [];
        }

        $latest = [];

        $tables = [
            (new PancakeUserPosDailyReport)->getTable(),
            (new PancakeUserDailyCallReport)->getTable(),
        ];

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->where('workspace_id', $workspace->id)
                ->whereIn('pancake_user_id', $pancakeUserIds)
                ->groupBy('pancake_user_id')
                ->selectRaw('pancake_user_id, MAX(date) as last_date')
                ->get();

            foreach ($rows as $row) {
                $current = $latest[$row->pancake_user_id] ?? null;

                // Both are `YYYY-MM-DD`, so the later date is the larger string.
                if ($row->last_date !== null && ($current === null || $row->last_date > $current)) {
                    $latest[$row->pancake_user_id] = $row->last_date;
                }
            }
        }

        return $latest;
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
     * Which CSR comparison metric the page opens on.
     *
     * Kept in the URL rather than the browser so a reload, a shared link and
     * the back button all land on the metric that was being read. Anything the
     * catalogue does not name falls back to total sales; the four keys the
     * panel used to tab between resolve to what they now point at, so older
     * links still open where they say.
     */
    private function comparisonTab(Request $request): string
    {
        return CsrComparisonMetrics::resolveKey($request->input('comparison'));
    }

    public function analytics(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to, $type] = $this->analyticsPeriod($request);

        $isErp = $type === 'erp';
        $drClass = $isErp ? PancakeUserErpDailyReport::class : PancakeUserPosDailyReport::class;

        // Per-CSR sales/delivery rollup for the selected period (POS or ERP).
        // Every column the report carries is summed, not only the ones the
        // table used to show.
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
            ')
            // The parcel counts behind those two money figures. Only the POS
            // rollup carries them, so the ERP side reads zero rather than
            // dropping the columns and changing the table's shape mid-toggle.
            ->selectRaw($isErp
                ? '0 as total_delivered_count, 0 as total_returning_count'
                : 'SUM(delivered_count) as total_delivered_count, SUM(returning_count) as total_returning_count');

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
                SUM(total_rmo_orders)          as total_rmo_orders,
                SUM(total_rmo_confirmed_count) as total_confirmed
            ')
            // The rest of the call report. The table's own `total_called` and
            // `total_call_time` — every call placed, RMO and verification alike
            // — are qualified because the aliases above have taken those two
            // names. The longest call is a max, so a range takes the max of the
            // days' maxes rather than adding them up.
            ->selectRaw('
                SUM(pancake_user_daily_call_reports.total_called)    as total_all_called,
                SUM(pancake_user_daily_call_reports.total_call_time) as total_all_call_time,
                SUM(total_rmo_connected_called)     as total_rmo_connected_called,
                SUM(total_rmo_real_called)          as total_rmo_real_called,
                MAX(longest_rmo_call_time)          as longest_rmo_call_time,
                SUM(total_rmo_customer_called)      as total_rmo_customer_called,
                SUM(total_rmo_customer_call_time)   as total_rmo_customer_call_time,
                SUM(total_rmo_rider_called)         as total_rmo_rider_called,
                SUM(total_rmo_rider_call_time)      as total_rmo_rider_call_time,
                SUM(total_verification_called)      as total_verification_called,
                SUM(total_verification_call_time)   as total_verification_call_time,
                SUM(total_verification_real_called) as total_verification_real_called,
                SUM(total_verified_orders)          as total_verified_orders
            ');

        $base = PancakeUser::query()
            ->whereHas('shops', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            });

        // Both rollups are keyed by shop, so the "viewing as team" switcher
        // narrows the table the same way it narrows the cards above it. The ERP
        // report carries no shop, so only the POS side can be scoped.
        $shopIds = TeamVisibility::scopeShopIds($request->user(), $workspace);

        if ($shopIds !== null) {
            $rmoSummary->whereIn('shop_id', $shopIds);

            if (! $isErp) {
                $drSummary->whereIn('shop_id', $shopIds);
            }
        }

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
            ->selectRaw('COALESCE(rmo.total_rmo_orders, 0)         as total_rmo_orders')
            ->selectRaw('COALESCE(rmo.total_confirmed, 0)          as total_confirmed')
            ->selectRaw('COALESCE(dr.total_delivered_count, 0)     as total_delivered_count')
            ->selectRaw('COALESCE(dr.total_returning_count, 0)     as total_returning_count')
            ->selectRaw('COALESCE(rmo.total_all_called, 0)         as total_all_called')
            ->selectRaw('COALESCE(rmo.total_all_call_time, 0)      as total_all_call_time')
            ->selectRaw('COALESCE(rmo.total_rmo_connected_called, 0)     as total_rmo_connected_called')
            ->selectRaw('COALESCE(rmo.total_rmo_real_called, 0)          as total_rmo_real_called')
            ->selectRaw('COALESCE(rmo.longest_rmo_call_time, 0)          as longest_rmo_call_time')
            ->selectRaw('COALESCE(rmo.total_rmo_customer_called, 0)      as total_rmo_customer_called')
            ->selectRaw('COALESCE(rmo.total_rmo_customer_call_time, 0)   as total_rmo_customer_call_time')
            ->selectRaw('COALESCE(rmo.total_rmo_rider_called, 0)         as total_rmo_rider_called')
            ->selectRaw('COALESCE(rmo.total_rmo_rider_call_time, 0)      as total_rmo_rider_call_time')
            ->selectRaw('COALESCE(rmo.total_verification_called, 0)      as total_verification_called')
            ->selectRaw('COALESCE(rmo.total_verification_call_time, 0)   as total_verification_call_time')
            ->selectRaw('COALESCE(rmo.total_verification_real_called, 0) as total_verification_real_called')
            ->selectRaw('COALESCE(rmo.total_verified_orders, 0)          as total_verified_orders')
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
            // Only the CSRs who did something in the range — see
            // BREAKDOWN_FIGURES. The clause reads the joined subqueries rather
            // than the aliases above it, which MySQL will not have resolved yet
            // at WHERE.
            ->where(function ($query) {
                foreach (self::BREAKDOWN_FIGURES as $figure) {
                    $query->orWhere($figure, '<>', 0);
                }
            })
            ->allowedSorts([
                AllowedSort::field('name', 'pancake_users.name'),
                'total_orders',
                'total_sales',
                'total_delivered',
                'total_returning',
                'total_called',
                'total_call_time',
                'total_rmo_call_attempts',
                'total_rmo_orders',
                'total_confirmed',
                'rts_rate',
                'rmo_percentage',
                'total_delivered_count',
                'total_returning_count',
                'total_all_called',
                'total_all_call_time',
                'total_rmo_connected_called',
                'total_rmo_real_called',
                'longest_rmo_call_time',
                'total_rmo_customer_called',
                'total_rmo_customer_call_time',
                'total_rmo_rider_called',
                'total_rmo_rider_call_time',
                'total_verification_called',
                'total_verification_call_time',
                'total_verification_real_called',
                'total_verified_orders',
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
            // The manual rollup trigger is a test-server convenience — production
            // keeps to the nightly schedule, so the button is not rendered there.
            // CSRController@runSync refuses the call on the same terms.
            'canRunSync' => ! app()->environment('production'),
            // Everything the comparison panel's selector lists. Shipped with
            // the page so the dropdown is populated on the first paint, rather
            // than filling in once the figures land behind it.
            'comparisonMetrics' => CsrComparisonMetrics::options(),
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

        // The column's default was lowercase `active` until a later migration
        // changed it to `ACTIVE`, and that change never touched the rows already
        // written. The dialog posts back whatever it was handed, so a CSR from
        // that window used to fail `in:` on its own stored value. Fold the case
        // before validating and write back the canonical spelling.
        $request->merge([
            'status' => is_string($status = $request->input('status'))
                ? strtoupper(trim($status))
                : $status,
        ]);

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

<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    use AuthorizesRequests;

    private const ALLOWED_SORTS = [
        'csr_name', 'total_orders', 'total_sales',
        'delivered', 'returning_count', 'rts_rate',
        'total_called', 'total_call_time',
    ];

    private function isPos(Request $request): bool
    {
        return strtolower((string) $request->input('type', 'pos')) !== 'erp';
    }

    private function reportTable(Request $request): string
    {
        return $this->isPos($request)
            ? (new PancakeUserPosDailyReport)->getTable()
            : (new PancakeUserErpDailyReport)->getTable();
    }

    private function range(Request $request): array
    {
        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        return [$from, $to];
    }

    private function posDailySummarySub(Workspace $workspace, string $from, string $to)
    {
        // csr_daily_records is the nightly POS rollup written by sync:csr-daily-records.
        // It already aggregates pancake_orders per (workspace, csr, date) with rmo_called
        // counted as pancake_order_for_delivery rows where status != 'PENDING'.
        // csr_id here is the system users.id, joined later via pancake_users.user_id.
        return DB::table('csr_daily_records')
            ->where('workspace_id', $workspace->id)
            ->where('type', 'POS')
            ->whereBetween('date', [$from, $to])
            ->groupBy('csr_id')
            ->selectRaw('
                csr_id,
                SUM(total_orders) as total_orders,
                SUM(total_sales) as total_sales,
                SUM(delivered) as delivered,
                SUM(`returning`) as returning_count,
                SUM(rmo_called) as total_called
            ');
    }

    private function erpDailySummarySub(Workspace $workspace, string $from, string $to)
    {
        return DB::table('pancake_user_erp_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_orders) as total_orders,
                SUM(total_sales) as total_sales,
                SUM(delivered) as delivered,
                SUM(`returning`) as returning_count
            ');
    }

    private function rmoCallTimeSub(Workspace $workspace, string $from, string $to)
    {
        return DB::table('pancake_user_rmo_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_call_time) as total_call_time,
                SUM(total_called) as total_called
            ');
    }

    public function dailyRecords(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        [$from, $to] = $this->range($request);

        $posSummary = PancakeUserPosDailyReport::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_orders)   as total_orders,
                SUM(total_sales)    as total_sales,
                SUM(`returning`)    as total_returning,
                SUM(delivered)      as total_delivered
            ');

        $rmoSummary = PancakeUserRmoDailyReport::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_called)             as total_called,
                SUM(total_call_time)          as total_call_time,
                SUM(total_rmo_call_attempts)  as total_rmo_call_attempts,
                SUM(total_confirmed)          as total_confirmed
            ');

        $base = User::query()
            ->whereHas('shopUsers.shop', fn ($q) => $q->where('workspace_id', $workspace->id));

        $records = QueryBuilder::for($base)
            ->leftJoinSub($posSummary, 'pos', 'pos.pancake_user_id', '=', 'pancake_users.id')
            ->leftJoinSub($rmoSummary, 'rmo', 'rmo.pancake_user_id', '=', 'pancake_users.id')
            ->select('pancake_users.*')
            ->selectRaw('COALESCE(pos.total_orders, 0)             as total_orders')
            ->selectRaw('COALESCE(pos.total_sales, 0)              as total_sales')
            ->selectRaw('COALESCE(pos.total_returning, 0)          as total_returning')
            ->selectRaw('COALESCE(pos.total_delivered, 0)          as total_delivered')
            ->selectRaw('COALESCE(rmo.total_called, 0)             as total_called')
            ->selectRaw('COALESCE(rmo.total_call_time, 0)          as total_call_time')
            ->selectRaw('COALESCE(rmo.total_rmo_call_attempts, 0)  as total_rmo_call_attempts')
            ->selectRaw('COALESCE(rmo.total_confirmed, 0)          as total_confirmed')
            ->selectRaw('
                CASE
                    WHEN (COALESCE(pos.total_returning, 0) + COALESCE(pos.total_delivered, 0)) > 0
                    THEN ROUND(
                        COALESCE(pos.total_returning, 0)
                        / (COALESCE(pos.total_returning, 0) + COALESCE(pos.total_delivered, 0))
                        * 100, 2
                    )
                    ELSE 0
                END as rts_rate
            ')
            ->allowedSorts([
                AllowedSort::field('csr_name', 'pancake_users.name'),
                'total_orders',
                'total_sales',
                'total_returning',
                'total_delivered',
                'total_called',
                'total_call_time',
                'total_rmo_call_attempts',
                'total_confirmed',
                'rts_rate',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($q, $value) {
                    $q->where('pancake_users.name', 'like', "%{$value}%");
                }),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return response()->json($records);
    }

    private function rollupStatQuery(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        return DB::table($this->reportTable($request))
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to]);
    }

    private function posSummaryQuery(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        return DB::table('csr_daily_records')
            ->where('workspace_id', $workspace->id)
            ->where('type', 'POS')
            ->whereBetween('date', [$from, $to]);
    }

    public function statTotalSales(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('total_sales')
            : $this->rollupStatQuery($request, $workspace)->sum('total_sales');

        return response()->json(['value' => $value]);
    }

    public function statTotalOrders(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('total_orders')
            : $this->rollupStatQuery($request, $workspace)->sum('total_orders');

        return response()->json(['value' => $value]);
    }

    public function statTotalDelivered(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('delivered')
            : $this->rollupStatQuery($request, $workspace)->sum('delivered');

        return response()->json(['value' => $value]);
    }

    public function statTotalReturning(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('returning')
            : $this->rollupStatQuery($request, $workspace)->sum('returning');

        return response()->json(['value' => $value]);
    }

    public function statTotalRts(Request $request, Workspace $workspace)
    {
        $row = ($this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)
            : $this->rollupStatQuery($request, $workspace))
            ->selectRaw('SUM(delivered) as d, SUM(`returning`) as r')
            ->first();

        $total = ($row->d ?? 0) + ($row->r ?? 0);
        $value = $total > 0 ? round(($row->r / $total) * 100, 2) : 0;

        return response()->json(['value' => $value]);
    }

    public function statTotalRmoCalled(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        if ($this->isPos($request)) {
            // POS rmo_called is pre-aggregated nightly into csr_daily_records
            // (count of pancake_order_for_delivery rows where status != 'PENDING').
            $value = DB::table('csr_daily_records')
                ->where('workspace_id', $workspace->id)
                ->where('type', 'POS')
                ->whereBetween('date', [$from, $to])
                ->sum('rmo_called');
        } else {
            $value = DB::table('pancake_user_rmo_daily_reports')
                ->where('workspace_id', $workspace->id)
                ->whereBetween('date', [$from, $to])
                ->sum('total_called');
        }

        return response()->json(['value' => $value]);
    }

    /*
     |--------------------------------------------------------------------------
     | CSR Analytics stat cards
     |--------------------------------------------------------------------------
     |
     | One endpoint per card, same as the stats above. What differs is the
     | source: these read the workspace's orders through WorkspaceMetrics — the
     | very same TotalSales / RtsRate the main dashboard reports — rather than
     | the nightly per-CSR rollup the `total-*` endpoints above sum.
     |
     | That difference is deliberate. The rollup only covers days
     | sync:csr-daily-records has run for, so a range it has not reached reads as
     | zero on a day the dashboard shows plenty. These cards are the headline
     | figure on the page, so they quote what the rest of the app calls total
     | sales; the per-CSR table underneath stays attribution, not the total.
     |
     | Each answers with `value` like its neighbours, plus what its own card
     | draws: the previous period's figure and the move between them.
     */

    public function analyticsSales(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->orderTotals($workspace, $from, $to);
        $previous = $this->orderTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['sales'],
            'orders' => $current['orders'],
            'previous_value' => $previous['sales'],
            'previous_orders' => $previous['orders'],
            // Null, not zero, when the previous period had no sales: there is no
            // percentage change from nothing, and 0% would read as "flat" when
            // it means "nothing to compare against".
            'change' => $previous['sales'] > 0
                ? round(($current['sales'] - $previous['sales']) / $previous['sales'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRts(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->orderTotals($workspace, $from, $to);
        $previous = $this->orderTotals($workspace, $previousFrom, $previousTo);

        // A period with no settled parcels has no RTS rate — the metric's SQL
        // coalesces that to 0, which is indistinguishable from a genuinely
        // perfect period. Measuring the volume tells them apart.
        $settled = $current['returning'] + $current['delivered'];
        $previousSettled = $previous['returning'] + $previous['delivered'];

        return response()->json([
            'value' => $settled > 0 ? round($current['rts'] * 100, 2) : null,
            'returning_amount' => $current['returning'],
            'previous_value' => $previousSettled > 0 ? round($previous['rts'] * 100, 2) : null,
            // Percentage *points*, not a relative change: 12% to 15% is "+3
            // pts". Reporting it as +25% would be arithmetically true and
            // completely unreadable on a card about a rate.
            'change' => $settled > 0 && $previousSettled > 0
                ? round(($current['rts'] - $previous['rts']) * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoCalled(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoTotals($workspace, $from, $to);
        $previous = $this->rmoTotals($workspace, $previousFrom, $previousTo);

        $rate = fn (array $t) => $t['assigned'] > 0 ? $t['called'] / $t['assigned'] * 100 : null;

        $currentRate = $rate($current);
        $previousRate = $rate($previous);

        return response()->json([
            // Null, not zero, when nothing was assigned in the range: 0% would
            // read as "nobody rang anyone" rather than "there was nothing to
            // ring".
            'value' => $currentRate === null ? null : round($currentRate, 2),
            'called' => $current['called'],
            'assigned' => $current['assigned'],
            'previous_value' => $previousRate === null ? null : round($previousRate, 2),
            // Percentage points, as on the RTS card — this is a rate, and a move
            // from 86.4% to 86.9% is "+0.5 pts", not "+0.6%".
            'change' => $currentRate !== null && $previousRate !== null
                ? round($currentRate - $previousRate, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoTime(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            // Talk time spread over the calls placed. Null with no calls at all
            // — an average of nothing is not zero seconds a call.
            'average_seconds' => $current['calls'] > 0
                ? round($current['seconds'] / $current['calls'], 1)
                : null,
            'previous_value' => $previous['seconds'],
            // A relative change, unlike the two rate cards: this is a magnitude,
            // so "9% more time on the phone" is the readable form.
            'change' => $previous['seconds'] > 0
                ? round(($current['seconds'] - $previous['seconds']) / $previous['seconds'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    /**
     * Time spent on the phone across a range, and how many calls made it up.
     *
     * Every call the workspace logged on those dates, flat — the same rule the
     * RMO management page's own call cards use, so the two agree. Deliberately
     * not narrowed to calls that match a delivery: a CSR spends the day ringing
     * numbers that are not on the orders assigned to them, and the `persona`
     * column that marks a matched call is only stamped on rows synced since it
     * was added, so leaning on it would read as zero for older ranges.
     *
     * @return array{seconds: int, calls: int}
     */
    private function rmoCallTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = DB::table('call_logs')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('call_date', [$from, $to])
            ->selectRaw('COUNT(*) as calls, COALESCE(SUM(duration), 0) as seconds')
            ->first();

        return [
            'seconds' => (int) ($row->seconds ?? 0),
            'calls' => (int) ($row->calls ?? 0),
        ];
    }

    /**
     * RMO deliveries assigned in a range, and how many of them were called.
     *
     * Straight off pancake_order_for_delivery rather than the nightly per-CSR
     * rollup, for the same reason the sales and RTS cards read orders: the card
     * has to be right for any range, including one the sync has not covered.
     *
     * "Assigned" is a delivery with an assignee — an unassigned row was nobody's
     * to call, so counting it would drag the rate down for no one's failing.
     * "Called" is the same rule the RMO page itself uses: any status off PENDING.
     *
     * @return array{assigned: int, called: int}
     */
    private function rmoTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = DB::table('pancake_order_for_delivery')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('assignee_id')
            ->whereBetween('delivery_date', [$from, $to])
            ->selectRaw("
                COUNT(*) as assigned,
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called
            ")
            ->first();

        return [
            'assigned' => (int) ($row->assigned ?? 0),
            'called' => (int) ($row->called ?? 0),
        ];
    }

    /**
     * The equally long stretch ending the day before $from.
     *
     * Aug 1–5 is measured against Jul 27–31: same length, so the two figures are
     * comparable; immediately before, so "up on last period" means the period a
     * user just scrolled past.
     *
     * @return array{0: string, 1: string}
     */
    private function previousRange(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from);
        $days = $start->diffInDays(CarbonImmutable::parse($to)) + 1;

        $previousTo = $start->subDay();

        return [$previousTo->subDays($days - 1)->toDateString(), $previousTo->toDateString()];
    }

    /**
     * Every order figure the analytics cards need, over one date range.
     *
     * @return array{sales: float, orders: int, rts: float, returning: float, delivered: float}
     */
    private function orderTotals(Workspace $workspace, string $from, string $to): array
    {
        $metrics = $workspace->metrics(
            ['start_date' => $from, 'end_date' => $to],
            [],
        )->extract(['totalSales', 'totalOrders', 'rtsRate', 'returningAmount', 'deliveredAmount']);

        return [
            'sales' => (float) $metrics['totalSales'],
            'orders' => (int) $metrics['totalOrders'],
            // A ratio, 0..1 — the card turns it into a percentage.
            'rts' => (float) $metrics['rtsRate'],
            'returning' => (float) $metrics['returningAmount'],
            'delivered' => (float) $metrics['deliveredAmount'],
        ];
    }
}

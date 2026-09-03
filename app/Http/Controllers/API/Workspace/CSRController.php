<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
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

    public function statTotalSales(Request $request, Workspace $workspace)
    {
        $value = $this->rollupStatQuery($request, $workspace)->sum('total_sales');

        return response()->json(['value' => $value]);
    }

    public function statTotalOrders(Request $request, Workspace $workspace)
    {
        $value = $this->rollupStatQuery($request, $workspace)->sum('total_orders');

        return response()->json(['value' => $value]);
    }

    public function statTotalDelivered(Request $request, Workspace $workspace)
    {
        $value = $this->rollupStatQuery($request, $workspace)->sum('delivered');

        return response()->json(['value' => $value]);
    }

    public function statTotalReturning(Request $request, Workspace $workspace)
    {
        $value = $this->rollupStatQuery($request, $workspace)->sum('returning');

        return response()->json(['value' => $value]);
    }

    public function statTotalRts(Request $request, Workspace $workspace)
    {
        $row = $this->rollupStatQuery($request, $workspace)
            ->selectRaw('SUM(delivered) as d, SUM(`returning`) as r')
            ->first();

        $total = ($row->d ?? 0) + ($row->r ?? 0);
        $value = $total > 0 ? round(($row->r / $total) * 100, 2) : 0;

        return response()->json(['value' => $value]);
    }

    public function statTotalRmoCalled(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        $value = DB::table('pancake_user_rmo_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->sum('total_called');

        return response()->json(['value' => $value]);
    }

    /*
     |--------------------------------------------------------------------------
     | CSR Analytics stat cards
     |--------------------------------------------------------------------------
     |
     | One endpoint per card, reading the workspace's orders through
     | WorkspaceMetrics — the dashboard's own figures, so they hold for a range
     | the nightly rollup has not reached. Each answers with `value`, the
     | previous period's figure and the move between them.
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
            // Null, not zero, with no previous sales: 0% would read as "flat".
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

        // No settled parcels means no rate. The metric coalesces that to 0,
        // which reads as a perfect period; the volume tells them apart.
        $settled = $current['returning'] + $current['delivered'];
        $previousSettled = $previous['returning'] + $previous['delivered'];

        return response()->json([
            'value' => $settled > 0 ? round($current['rts'] * 100, 2) : null,
            'returning_amount' => $current['returning'],
            'previous_value' => $previousSettled > 0 ? round($previous['rts'] * 100, 2) : null,
            // Percentage points, not a relative move: 12% to 15% is "+3 pts".
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
            // Null, not zero, with nothing assigned: 0% would read as "nobody rang".
            'value' => $currentRate === null ? null : round($currentRate, 2),
            'called' => $current['called'],
            'assigned' => $current['assigned'],
            'previous_value' => $previousRate === null ? null : round($previousRate, 2),
            // Percentage points, as on the RTS card — this is a rate.
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
            // Talk time over the calls placed; null with no calls to divide by.
            'average_seconds' => $current['calls'] > 0
                ? round($current['seconds'] / $current['calls'], 1)
                : null,
            'previous_value' => $previous['seconds'],
            // Relative, unlike the rate cards: this is a magnitude.
            'change' => $previous['seconds'] > 0
                ? round(($current['seconds'] - $previous['seconds']) / $previous['seconds'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsCallsPlaced(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            // Every call placed against an order, however short.
            'value' => $current['calls'],
            // The subset that connected, so the card can say how many landed.
            'connected' => $current['connected'],
            'previous_value' => $previous['calls'],
            // Relative, like the time card: a count is a magnitude, not a rate.
            'change' => $previous['calls'] > 0
                ? round(($current['calls'] - $previous['calls']) / $previous['calls'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRealConversations(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['real'],
            'placed' => $current['calls'],
            // What share of the attempts became a conversation — 40 of 60 and
            // 40 of 4,000 are not the same day's work.
            'share' => $current['calls'] > 0
                ? round($current['real'] / $current['calls'] * 100, 1)
                : null,
            'previous_value' => $previous['real'],
            'change' => $previous['real'] > 0
                ? round(($current['real'] - $previous['real']) / $previous['real'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsReachRate(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        // How often picking up the phone reached somebody: the conversations
        // that lasted, over every attempt made.
        $rate = fn (array $t) => $t['calls'] > 0 ? $t['real'] / $t['calls'] * 100 : null;

        $currentRate = $rate($current);
        $previousRate = $rate($previous);

        return response()->json([
            // Null, not zero, with no attempts: 0% would read as "reached nobody".
            'value' => $currentRate === null ? null : round($currentRate, 1),
            'real' => $current['real'],
            'placed' => $current['calls'],
            'previous_value' => $previousRate === null ? null : round($previousRate, 1),
            // Percentage points, as on the other rate cards.
            'change' => $currentRate !== null && $previousRate !== null
                ? round($currentRate - $previousRate, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsLongestCall(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        // The day it happened, for the footnote — a second tiny query because
        // MAX() gives the length, not the row it came from.
        $longest = $current['longest'] > 0
            ? DB::table('call_logs')
                ->where('workspace_id', $workspace->id)
                ->whereNotNull('order_id')
                ->whereBetween('call_date', [$from, $to])
                ->orderByDesc('duration')
                ->first(['call_date', 'order_id'])
            : null;

        return response()->json([
            'value' => $current['longest'],
            'call_date' => $longest?->call_date,
            'order_id' => $longest?->order_id,
            'previous_value' => $previous['longest'],
            // Relative: a duration is a magnitude, not a rate.
            'change' => $previous['longest'] > 0
                ? round(($current['longest'] - $previous['longest']) / $previous['longest'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    /**
     * Calls placed against real conversations, one point per day.
     *
     * Same source and rules as the call cards, so a day here agrees with the
     * card above it. Every day in the range is returned, zeros included.
     */
    public function analyticsDailyEffort(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $rows = DB::table('call_logs')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('order_id')
            ->whereBetween('call_date', [$from, $to])
            ->groupBy('call_date')
            ->selectRaw('
                call_date,
                COUNT(*) as calls,
                COUNT(CASE WHEN duration >= '.RmoDailyStats::CONNECTED_CALL_MIN_SECONDS.' THEN 1 END) as real_conversations
            ')
            ->get()
            // call_date comes back with or without a time part depending on the
            // driver — key on the first ten characters.
            ->keyBy(fn ($row) => substr((string) $row->call_date, 0, 10));

        $days = [];
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $days[] = [
                'date' => $date,
                'calls' => (int) ($row->calls ?? 0),
                'real' => (int) ($row->real_conversations ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'totals' => [
                'calls' => array_sum(array_column($days, 'calls')),
                'real' => array_sum(array_column($days, 'real')),
            ],
        ]);
    }

    /**
     * Where each day's calls ended up — the table under the effort chart.
     *
     * Three buckets narrowing in turn: no_answer never joined, answered picked
     * up, conversations lasted past the five-second threshold. So calls =
     * no_answer + answered, and conversations is a cut of answered. `hit_rate`
     * is conversations over every attempt — the reach rate card, per day.
     */
    public function analyticsDailyCallOutcomes(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $rows = DB::table('call_logs')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('order_id')
            ->whereBetween('call_date', [$from, $to])
            ->groupBy('call_date')
            ->selectRaw('
                call_date,
                COUNT(*) as calls,
                COUNT(CASE WHEN duration > 0 THEN 1 END) as answered,
                COUNT(CASE WHEN duration >= '.RmoDailyStats::CONNECTED_CALL_MIN_SECONDS.' THEN 1 END) as real_conversations
            ')
            ->get()
            // call_date comes back with or without a time part depending on the
            // driver — key on the first ten characters.
            ->keyBy(fn ($row) => substr((string) $row->call_date, 0, 10));

        $days = [];
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $calls = (int) ($row->calls ?? 0);
            $answered = (int) ($row->answered ?? 0);
            $real = (int) ($row->real_conversations ?? 0);

            // Every day gets a row, quiet ones included.
            $days[] = [
                'date' => $date,
                'calls' => $calls,
                'no_answer' => $calls - $answered,
                'answered' => $answered,
                'conversations' => $real,
                'hit_rate' => $calls > 0 ? round($real / $calls * 100, 1) : null,
            ];

            $cursor = $cursor->addDay();
        }

        $calls = array_sum(array_column($days, 'calls'));
        $conversations = array_sum(array_column($days, 'conversations'));

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'totals' => [
                'calls' => $calls,
                'no_answer' => array_sum(array_column($days, 'no_answer')),
                'answered' => array_sum(array_column($days, 'answered')),
                'conversations' => $conversations,
                // The period's own rate, not the mean of the daily ones.
                'hit_rate' => $calls > 0 ? round($conversations / $calls * 100, 1) : null,
            ],
        ]);
    }

    /**
     * Time spent on RMO calls across a range, and the calls behind it.
     *
     * Only calls carrying an order_id — matched to a delivery at sync time by
     * CallLogPersona, with no backfill, so this covers calls synced from that
     * point on. `connected` is any talk time at all, `real` the stricter cut at
     * RmoDailyStats' five-second threshold, `longest` the single longest call.
     *
     * @return array{seconds: int, calls: int, connected: int, real: int, longest: int}
     */
    private function rmoCallTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = DB::table('call_logs')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('order_id')
            ->whereBetween('call_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as calls,
                COALESCE(SUM(duration), 0) as seconds,
                COUNT(CASE WHEN duration > 0 THEN 1 END) as connected,
                COUNT(CASE WHEN duration >= '.RmoDailyStats::CONNECTED_CALL_MIN_SECONDS.' THEN 1 END) as real_conversations,
                COALESCE(MAX(duration), 0) as longest
            ')
            ->first();

        return [
            'seconds' => (int) ($row->seconds ?? 0),
            'calls' => (int) ($row->calls ?? 0),
            'connected' => (int) ($row->connected ?? 0),
            'real' => (int) ($row->real_conversations ?? 0),
            'longest' => (int) ($row->longest ?? 0),
        ];
    }

    /**
     * RMO deliveries assigned in a range, and how many were called.
     *
     * Straight off pancake_order_for_delivery, so the card is right for a range
     * the sync has not covered. "Assigned" needs an assignee — an unassigned row
     * was nobody's to call. "Called" is any status off PENDING, as the RMO page.
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

    /*
     |--------------------------------------------------------------------------
     | Leaders for the period
     |--------------------------------------------------------------------------
     |
     | Who came top, rather than what the workspace did. All four read the
     | nightly rollups the CSR breakdown table reads, so a leader's figure is
     | that CSR's row — and a range the sync has not covered has no leader.
     */

    public function analyticsLeaderSales(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        // Off the rollup, so this is the row the CSR table shows: everything
        // confirmed, cancellations included — not the Sales card's rule.
        $perCsr = DB::table('pancake_user_pos_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$from, $to])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.total_sales), 0) as sales,
                COALESCE(SUM(r.total_orders), 0) as orders
            ')
            // A rollup row for a day they only had a parcel settle confirmed
            // nothing, so it is not a contender.
            ->havingRaw('orders > 0')
            ->orderByDesc('sales')
            // Ties break on volume, so the same data always crowns the same person.
            ->orderByDesc('orders')
            ->get();

        $leader = $perCsr->first();

        if ($leader === null) {
            return response()->json(['leader' => null]);
        }

        // The CSRs' own total, not the workspace's — an order confirmed by
        // nobody never reaches the rollup, so the shares here add up to 100%.
        $total = (float) $perCsr->sum('sales');
        $sales = (float) $leader->sales;
        $orders = (int) $leader->orders;

        return response()->json([
            'leader' => [
                'name' => $leader->name,
                'value' => $sales,
                'orders' => $orders,
                // What one order was worth on average to this CSR.
                'aov' => $orders > 0 ? round($sales / $orders, 2) : null,
                // Their slice of everything the CSRs confirmed; null when that
                // total is somehow zero.
                'share' => $total > 0 ? round($sales / $total * 100, 1) : null,
            ],
        ]);
    }

    public function analyticsLeaderRts(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        // Off the same rollup, so this is the RTS Rate column: money back over
        // money settled, counted on the day it settled. Replaces a scan of the
        // workspace's whole order history; an uncovered range has nobody to rank.
        $perCsr = DB::table('pancake_user_pos_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$from, $to])
            ->groupBy('pu.id', 'pu.name')
            // `_amount` suffixes on purpose: an alias of `delivered` shadows
            // r.delivered in the ORDER BY, which ONLY_FULL_GROUP_BY rejects.
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.returning), 0) as returned_amount,
                COALESCE(SUM(r.delivered), 0) as delivered_amount,
                COALESCE(SUM(r.returning_count + r.delivered_count), 0) as settled_orders
            ')
            // Eligibility is money settled, as on the RTS card. Parcels all
            // worth zero have no rate to rank, so they are out rather than last.
            ->havingRaw('returned_amount + delivered_amount > 0')
            ->orderByRaw('returned_amount / (returned_amount + delivered_amount) ASC')
            // Ties are common at a clean 0%; break them on volume.
            ->orderByDesc('settled_orders')
            ->first();

        if ($perCsr === null) {
            return response()->json(['leader' => null]);
        }

        $returned = (float) $perCsr->returned_amount;
        $delivered = (float) $perCsr->delivered_amount;
        $settled = $returned + $delivered;

        return response()->json([
            'leader' => [
                'name' => $perCsr->name,
                'value' => round($returned / $settled * 100, 1),
                'returned' => $returned,
                'delivered' => $delivered,
                'orders' => (int) $perCsr->settled_orders,
            ],
        ]);
    }

    public function analyticsLeaderRmoCalled(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        // The CSR table's own RMO % — total_called (deliveries assigned to them
        // that moved off PENDING) over total_confirmed (deliveries they confirmed).
        $leader = DB::table('pancake_user_rmo_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$from, $to])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.total_called), 0) as called,
                COALESCE(SUM(r.total_confirmed), 0) as confirmed
            ')
            // Nothing confirmed is no rate at all, not a zero one.
            ->havingRaw('confirmed > 0')
            ->orderByRaw('called / confirmed DESC')
            // Ties break on volume, so the same data always crowns the same person.
            ->orderByDesc('confirmed')
            ->first();

        if ($leader === null) {
            return response()->json(['leader' => null]);
        }

        $called = (int) $leader->called;
        $confirmed = (int) $leader->confirmed;

        return response()->json([
            'leader' => [
                'name' => $leader->name,
                'value' => round($called / $confirmed * 100, 1),
                'called' => $called,
                'confirmed' => $confirmed,
            ],
        ]);
    }

    public function analyticsLeaderRmoDuration(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        // The CSR table's "RMO Call Time" summed over the range, averaged over
        // the call count sitting beside it in "RMO Called".
        $leader = DB::table('pancake_user_rmo_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$from, $to])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.total_call_time), 0) as seconds,
                COALESCE(SUM(r.total_rmo_call_attempts), 0) as calls
            ')
            // Without this the card would crown somebody at 00:00 on a quiet week.
            ->havingRaw('seconds > 0')
            ->orderByDesc('seconds')
            ->first();

        if ($leader === null) {
            return response()->json(['leader' => null]);
        }

        $seconds = (int) $leader->seconds;
        $calls = (int) $leader->calls;

        return response()->json([
            'leader' => [
                'name' => $leader->name,
                'value' => $seconds,
                'calls' => $calls,
                // Null when the rollup recorded time but no calls: the two
                // columns are written independently.
                'average_seconds' => $calls > 0 ? round($seconds / $calls, 1) : null,
            ],
        ]);
    }

    /**
     * The equally long stretch ending the day before $from — so Aug 1–5 is
     * measured against Jul 27–31.
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

    /*
     |--------------------------------------------------------------------------
     | CSR comparison
     |--------------------------------------------------------------------------
     |
     | The field behind the leaders: every CSR on one axis, against the period's
     | average and their own previous figure. One endpoint for all four metrics —
     | they come from two rollup scans that each already carry both of theirs, so
     | a request per tab would run the same two queries twice over.
     */

    /** How many CSRs a metric lists. Matches the eight-slot chart palette. */
    private const COMPARISON_ROWS = 8;

    public function analyticsComparison(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $csrs = $this->comparisonOrderFigures($workspace, $from, $to, $previousFrom, $previousTo);
        $rmo = $this->comparisonRmoFigures($workspace, $from, $to, $previousFrom, $previousTo);

        $metrics = [
            $this->comparisonMetric('sales', 'Sales', 'currency', '%', true, $csrs,
                fn ($r) => $r->sales > 0 ? (float) $r->sales : null,
                fn ($r) => $r->previous_sales > 0 ? (float) $r->previous_sales : null,
            ),
            $this->comparisonMetric('rts', 'RTS', 'percent', ' pts', false, $csrs,
                // Eligibility is one settled parcel counted, as on the RTS leader.
                fn ($r) => $r->returned_amount + $r->delivered_amount > 0 ? $this->rate($r->returned_amount, $r->returned_amount + $r->delivered_amount) : null,
                fn ($r) => $r->previous_returned_amount + $r->previous_delivered_amount > 0 ? $this->rate($r->previous_returned_amount, $r->previous_returned_amount + $r->previous_delivered_amount) : null,
            ),
            $this->comparisonMetric('rmo_called', 'RMO called', 'percent', ' pts', true, $rmo,
                // Nothing confirmed is no rate at all, not a zero one.
                fn ($r) => $r->confirmed > 0 ? $this->rate($r->called, $r->confirmed) : null,
                fn ($r) => $r->previous_confirmed > 0 ? $this->rate($r->previous_called, $r->previous_confirmed) : null,
            ),
            $this->comparisonMetric('call_time', 'Call time', 'duration', '%', true, $rmo,
                fn ($r) => $r->seconds > 0 ? (float) $r->seconds : null,
                fn ($r) => $r->previous_seconds > 0 ? (float) $r->previous_seconds : null,
            ),
        ];

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
            'metrics' => $this->withColourSlots($metrics),
        ]);
    }

    /** A percentage to one decimal; null when there is nothing to divide by. */
    private function rate(float|int|string $part, float|int|string $whole): ?float
    {
        return (float) $whole > 0 ? round((float) $part / (float) $whole * 100, 1) : 0.0;
    }

    /**
     * Sales and RTS per CSR, both periods, in one scan.
     *
     * Off the same rollup as the leader cards, so the field and the winner agree.
     * The periods are contiguous, so one indexed range covers both and the
     * conditional sums split them apart; an uncovered range is empty here.
     */
    private function comparisonOrderFigures(Workspace $workspace, string $from, string $to, string $previousFrom, string $previousTo)
    {
        return DB::table('pancake_user_pos_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$previousFrom, $to])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.id as id,
                pu.name as name,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_sales END), 0) as sales,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.returning END), 0) as returned_amount,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.delivered END), 0) as delivered_amount,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_sales END), 0) as previous_sales,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.returning END), 0) as previous_returned_amount,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.delivered END), 0) as previous_delivered_amount
            ', [
                $from, $to, $from, $to, $from, $to,
                $previousFrom, $previousTo, $previousFrom, $previousTo, $previousFrom, $previousTo,
            ])
            ->get();
    }

    /**
     * RMO % and talk time per CSR, both periods, off the nightly rollup — the
     * same rows the CSR table and the two RMO leaders read.
     */
    private function comparisonRmoFigures(Workspace $workspace, string $from, string $to, string $previousFrom, string $previousTo)
    {
        return DB::table('pancake_user_rmo_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$previousFrom, $to])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.id as id,
                pu.name as name,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_called END), 0) as called,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_confirmed END), 0) as confirmed,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_call_time END), 0) as seconds,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_called END), 0) as previous_called,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_confirmed END), 0) as previous_confirmed,
                COALESCE(SUM(CASE WHEN r.date BETWEEN ? AND ? THEN r.total_call_time END), 0) as previous_seconds
            ', [
                $from, $to, $from, $to, $from, $to,
                $previousFrom, $previousTo, $previousFrom, $previousTo, $previousFrom, $previousTo,
            ])
            ->get();
    }

    /**
     * One metric block: the CSRs who qualify, ranked, with the period's average.
     * That average is over everyone who qualified, not the listed rows — a top
     * eight measured against its own mean is half above average by construction.
     */
    private function comparisonMetric(
        string $key,
        string $label,
        string $format,
        string $deltaUnit,
        bool $higherIsBetter,
        $rows,
        callable $value,
        callable $previousValue,
    ): array {
        $eligible = $rows
            ->map(fn ($row) => [
                // pancake_users.id is a UUID — casting it to an int lands every
                // CSR on 0, quietly merging them.
                'id' => (string) $row->id,
                'name' => $row->name,
                'value' => $value($row),
                'previous_value' => $previousValue($row),
            ])
            ->filter(fn ($row) => $row['value'] !== null)
            ->values();

        $ranked = ($higherIsBetter
            ? $eligible->sortByDesc('value')
            : $eligible->sortBy('value')
        )->values();

        return [
            'key' => $key,
            'label' => $label,
            'format' => $format,
            // Percentage points for the metrics that are already rates, as on
            // the stat cards above.
            'delta_unit' => $deltaUnit,
            'higher_is_better' => $higherIsBetter,
            'average' => $eligible->isNotEmpty() ? round($eligible->avg('value'), 2) : null,
            'total' => $eligible->count(),
            'rows' => $ranked->take(self::COMPARISON_ROWS)->map(fn ($row) => [
                ...$row,
                'change' => $this->comparisonChange($row['value'], $row['previous_value'], $deltaUnit),
            ])->all(),
        ];
    }

    /**
     * This period against the one before, in the metric's own unit. Null rather
     * than zero with nothing to compare against — 0 would read as "flat".
     */
    private function comparisonChange(float $value, ?float $previous, string $deltaUnit): ?float
    {
        if ($previous === null) {
            return null;
        }

        if ($deltaUnit === ' pts') {
            return round($value - $previous, 1);
        }

        return $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null;
    }

    /**
     * A fixed chart colour per CSR across all four metrics, keyed to the person
     * rather than their rank, so flipping tabs moves the bars without repainting
     * them. Past eight CSRs the slots wrap; the name label carries identity.
     */
    private function withColourSlots(array $metrics): array
    {
        $order = collect($metrics)
            ->firstWhere('key', 'sales')['rows'] ?? [];

        $slots = collect($order)->pluck('id')->all();

        foreach ($metrics as $metric) {
            foreach ($metric['rows'] as $row) {
                if (! in_array($row['id'], $slots, true)) {
                    $slots[] = $row['id'];
                }
            }
        }

        $slots = array_flip($slots);

        return collect($metrics)->map(fn ($metric) => [
            ...$metric,
            'rows' => collect($metric['rows'])->map(fn ($row) => [
                ...$row,
                'color_slot' => $slots[$row['id']] % 8,
            ])->all(),
        ])->all();
    }
}

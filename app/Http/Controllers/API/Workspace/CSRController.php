<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Workspace;
use App\Support\CsrComparisonMetrics;
use App\Support\RmoDailyStats;
use App\Support\TeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    /**
     * The two nightly CSR rollups the analytics page reads — the same commands
     * routes/console.php schedules at 03:00 and 04:15. The button runs both at
     * once; the schedule stays as it is.
     */
    private const SYNC_COMMANDS = [
        'sync:csr-daily-records',
        'sync:csr-daily-call-records',
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

        $value = $this->callReport($workspace, $from, $to)->sum('total_rmo_assigned_count');

        return response()->json(['value' => $value]);
    }

    /*
     |--------------------------------------------------------------------------
     | CSR Analytics stat cards
     |--------------------------------------------------------------------------
     |
     | One endpoint per card. Sales and RTS read the nightly POS rollup — the
     | same pancake_user_pos_daily_reports rows the leaders, the comparison and
     | the table below them read, so the period's totals are the sum of the
     | CSRs listed under them. The RMO cards read the call report and the
     | delivery rows. Each answers with `value`, the previous period's figure
     | and the move between them.
     */

    public function analyticsSales(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->posTotals($workspace, $from, $to);
        $previous = $this->posTotals($workspace, $previousFrom, $previousTo);

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

        $current = $this->posTotals($workspace, $from, $to);
        $previous = $this->posTotals($workspace, $previousFrom, $previousTo);

        // Returning over returning plus delivered, summed over the range. Null,
        // not zero, when nothing in the range settled: a 0% would read as a
        // perfect period rather than an unfinished one.
        $rate = $current['rts_rate'];
        $previousRate = $previous['rts_rate'];

        return response()->json([
            'value' => $rate === null ? null : round($rate, 2),
            'returning_amount' => $current['returning'],
            'previous_value' => $previousRate === null ? null : round($previousRate, 2),
            // Percentage points, not a relative move: 12% to 15% is "+3 pts".
            'change' => $rate !== null && $previousRate !== null
                ? round($rate - $previousRate, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoCalled(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->callTotals($workspace, $from, $to)['calls'];
        $previous = $this->callTotals($workspace, $previousFrom, $previousTo)['calls'];

        return response()->json([
            'value' => $current,
            'previous_value' => $previous,
            // Relative, unlike the rate cards: this is a count.
            'change' => $previous > 0
                ? round(($current - $previous) / $previous * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsTotalRmoCalled(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCalledTotals($workspace, $from, $to);
        $previous = $this->rmoCalledTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['calls'],
            // The talk time behind them; RMO has no card of its own for it.
            'seconds' => $current['seconds'],
            'previous_value' => $previous['calls'],
            // Relative, unlike the rate cards: this is a count.
            'change' => $previous['calls'] > 0
                ? round(($current['calls'] - $previous['calls']) / $previous['calls'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoCallTime(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCalledTotals($workspace, $from, $to);
        $previous = $this->rmoCalledTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            // Talk time over the RMO calls behind it, which is the card beside
            // this one; null with none to divide by.
            'average_seconds' => $current['calls'] > 0
                ? round($current['seconds'] / $current['calls'], 1)
                : null,
            'previous_value' => $previous['seconds'],
            // Relative, like the other time cards: a duration is a magnitude.
            'change' => $previous['seconds'] > 0
                ? round(($current['seconds'] - $previous['seconds']) / $previous['seconds'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoRealConversations(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoRealTotals($workspace, $from, $to);
        $previous = $this->rmoRealTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['real'],
            'calls' => $current['calls'],
            // The share of RMO calls that got past the threshold; null with no
            // calls to divide by.
            'rate' => $current['calls'] > 0
                ? round($current['real'] / $current['calls'] * 100, 1)
                : null,
            'previous_value' => $previous['real'],
            // Relative, like the other counts: this is not a rate.
            'change' => $previous['real'] > 0
                ? round(($current['real'] - $previous['real']) / $previous['real'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoHitRate(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoRealTotals($workspace, $from, $to);
        $previous = $this->rmoRealTotals($workspace, $previousFrom, $previousTo);

        // Undefined rather than zero with nothing placed: 0% reads as "everyone
        // hung up", which is not "nobody called" — the same distinction
        // RmoDailyStats::derivedCallStats() makes for the RMO management card.
        $rate = $current['calls'] > 0
            ? round($current['real'] / $current['calls'] * 100, 1)
            : null;
        $previousRate = $previous['calls'] > 0
            ? round($previous['real'] / $previous['calls'] * 100, 1)
            : null;

        return response()->json([
            'value' => $rate,
            'conversations' => $current['real'],
            'calls' => $current['calls'],
            'previous_value' => $previousRate,
            // Percentage points, like the other rate cards: 40% to 45% is "+5 pts".
            'change' => $rate !== null && $previousRate !== null
                ? round($rate - $previousRate, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsRmoTime(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->callTotals($workspace, $from, $to);
        $previous = $this->callTotals($workspace, $previousFrom, $previousTo);

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

        $current = $this->verificationTotals($workspace, $from, $to);
        $previous = $this->verificationTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['calls'],
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

        $current = $this->verificationTotals($workspace, $from, $to);
        $previous = $this->verificationTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            // Talk time over the calls behind it; null with none to divide by.
            'average_seconds' => $current['calls'] > 0
                ? round($current['seconds'] / $current['calls'], 1)
                : null,
            'previous_value' => $previous['seconds'],
            // Relative, like the other time card: a duration is a magnitude.
            'change' => $previous['seconds'] > 0
                ? round(($current['seconds'] - $previous['seconds']) / $previous['seconds'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    public function analyticsReachRate(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->verificationBacklog($workspace, $from, $to);
        $previous = $this->verificationBacklog($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['needs_verification'],
            // The two reasons, so the card can say which is driving it.
            'no_report' => $current['no_report'],
            'high_rts' => $current['high_rts'],
            'orders' => $current['orders'],
            'previous_value' => $previous['needs_verification'],
            // Relative: this is a count of orders, not a rate.
            'change' => $previous['needs_verification'] > 0
                ? round((
                    $current['needs_verification'] - $previous['needs_verification']
                ) / $previous['needs_verification'] * 100, 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    /**
     * How much of the range's verification backlog got verified.
     *
     * Orders verified over "Total Order needs Verification" — orders on both
     * sides, so three calls at one order cover one order rather than three.
     * Counting the calls instead is what read a two-order backlog rung three
     * times as 150%.
     *
     * The counts behind the rate come back with it: the orders verified, the
     * backlog they are read against, and the calls it took to get through them
     * — an order rung three times is three calls and one order.
     */
    public function analyticsVerifiedOrders(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->verifiedCoverage($workspace, $from, $to);
        $previous = $this->verifiedCoverage($workspace, $previousFrom, $previousTo);

        return response()->json([
            'value' => $current['rate'],
            // Both sides of the division, so the card can show its own working,
            // and the calls behind the orders — the effort against the coverage.
            'orders' => $current['orders'],
            'calls' => $current['calls'],
            'needs_verification' => $current['needs_verification'],
            'previous_value' => $previous['rate'],
            // Percentage points, as on the other rate cards.
            'change' => $current['rate'] !== null && $previous['rate'] !== null
                ? round($current['rate'] - $previous['rate'], 1)
                : null,
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
        ]);
    }

    /**
     * Calls placed against real conversations, one point per day, split by the
     * kind of call.
     *
     * Same source and rules as the call cards, so a day here agrees with the
     * card above it. Every day in the range is returned, zeros included.
     *
     * Both kinds of call come back on every day: `calls`/`real` are the RMO
     * ones the cards report, `verification_calls`/`verification_real` the
     * order-verification ones beside them. The chart draws either the pair
     * stacked or the RMO half alone, and switching between the two is a
     * client-side filter — one request answers both.
     */
    public function analyticsDailyEffort(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $rows = $this->callReport($workspace, $from, $to)
            ->groupBy('date')
            ->selectRaw('
                date,
                COALESCE(SUM(total_rmo_called), 0) as calls,
                COALESCE(SUM(total_rmo_real_called), 0) as real_conversations,
                COALESCE(SUM(total_verification_called), 0) as verification_calls,
                COALESCE(SUM(total_verification_real_called), 0) as verification_real
            ')
            ->get()
            // date comes back with or without a time part depending on the
            // driver — key on the first ten characters.
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

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
                'verification_calls' => (int) ($row->verification_calls ?? 0),
                'verification_real' => (int) ($row->verification_real ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'totals' => [
                'calls' => array_sum(array_column($days, 'calls')),
                'real' => array_sum(array_column($days, 'real')),
                'verification_calls' => array_sum(array_column($days, 'verification_calls')),
                'verification_real' => array_sum(array_column($days, 'verification_real')),
            ],
        ]);
    }

    /**
     * The same effort and results, laid across the hours of a day.
     *
     * The chart above this one reads the nightly rollup, which is keyed to a
     * date and so cannot say when in the day the work happened. This one goes
     * back to the call log that rollup is built from and groups by the hour
     * stamped on each call.
     *
     * Every day in the range comes back with its own round of the clock rather
     * than the range arriving pre-flattened: the chart adds the days together
     * for its default view, but an hour of a Tuesday and the same hour of a
     * Saturday are still different things, and keeping the days apart here is
     * what lets it open a single one without another request.
     *
     * The rules are the sync's own, so a day here adds up to the day above it:
     * an order beside the call is what names the shop, a delivery stamp is what
     * makes it RMO work rather than order verification, and a conversation is a
     * call that lasted past RmoDailyStats::CONNECTED_CALL_MIN_SECONDS. Reading
     * the log direct does mean this chart covers today, where the daily one is
     * only as fresh as the last rollup.
     *
     * Every day in the range is returned, and every one of its 24 hours, zeros
     * included.
     */
    public function analyticsHourlyEffort(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $real = RmoDailyStats::CONNECTED_CALL_MIN_SECONDS;

        $rows = $this->callLogs($workspace, $from, $to)
            ->groupByRaw('cl.call_date, HOUR(cl.call_time)')
            ->selectRaw("
                cl.call_date as date,
                HOUR(cl.call_time) as hour,

                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL THEN 1 ELSE 0 END) as calls,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.duration >= {$real} THEN 1 ELSE 0 END) as real_conversations,

                SUM(CASE WHEN cl.order_for_delivery_id IS NULL THEN 1 ELSE 0 END) as verification_calls,
                SUM(CASE WHEN cl.order_for_delivery_id IS NULL AND cl.duration >= {$real} THEN 1 ELSE 0 END) as verification_real
            ")
            ->get()
            // date comes back with or without a time part depending on the
            // driver — key on the first ten characters.
            ->groupBy(fn ($row) => substr((string) $row->date, 0, 10));

        $days = [];
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $byHour = ($rows->get($date) ?? collect())->keyBy(fn ($row) => (int) $row->hour);

            $hours = [];

            for ($hour = 0; $hour < 24; $hour++) {
                $row = $byHour->get($hour);

                $hours[] = [
                    'hour' => $hour,
                    'calls' => (int) ($row->calls ?? 0),
                    'real' => (int) ($row->real_conversations ?? 0),
                    'verification_calls' => (int) ($row->verification_calls ?? 0),
                    'verification_real' => (int) ($row->verification_real ?? 0),
                ];
            }

            $days[] = [
                'date' => $date,
                'hours' => $hours,
                // The day's own figures, so the picker can open on a day that
                // has something to show without adding up 24 hours to find out.
                'totals' => $this->sumEffort($hours),
            ];

            $cursor = $cursor->addDay();
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'days' => $days,
            // The range as a whole, which is the daily chart's totals over the
            // same range — only the grouping differs, never the counting.
            'totals' => $this->sumEffort(array_merge(...array_column($days, 'hours'))),
        ]);
    }

    /**
     * The four effort figures added up over whatever rows carry them.
     *
     * @param  array<int, array<string, int>>  $rows
     * @return array{calls: int, real: int, verification_calls: int, verification_real: int}
     */
    private function sumEffort(array $rows): array
    {
        return [
            'calls' => array_sum(array_column($rows, 'calls')),
            'real' => array_sum(array_column($rows, 'real')),
            'verification_calls' => array_sum(array_column($rows, 'verification_calls')),
            'verification_real' => array_sum(array_column($rows, 'verification_real')),
        ];
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
    /**
     * How much of the range's verification work actually got done.
     *
     * Orders verified over the orders that needed one — the two cards beside
     * this one, divided. Null rather than zero with nothing to verify: a 0%
     * would read as "nobody rang" instead of "there was nothing to ring".
     *
     * Orders on both sides of the division, not calls: an order rung three
     * times covers one order, and counting the three would put two orders rung
     * between them at 150% verified. The calls come back beside the rate all
     * the same, as the effort behind the coverage.
     *
     * It can still pass 100%, for two reasons that are real signal rather than
     * arithmetic. A CSR can ring an order nothing flagged, and the distinct is
     * only taken within a CSR's day on a shop — an order chased across two days
     * counts on each of them.
     *
     * The two sides are also counted on different days: a call belongs to the
     * day it was placed, an order to the day it was confirmed. Over a range of
     * any length that washes out, but a single-day range can read oddly when
     * the calls chase the day before's orders.
     *
     * @return array{rate: float|null, orders: int, calls: int, needs_verification: int}
     */
    private function verifiedCoverage(Workspace $workspace, string $from, string $to): array
    {
        $verification = $this->verificationTotals($workspace, $from, $to);
        $needed = $this->verificationBacklog($workspace, $from, $to)['needs_verification'];

        return [
            'rate' => $needed > 0 ? round($verification['orders'] / $needed * 100, 1) : null,
            'orders' => $verification['orders'],
            'calls' => $verification['calls'],
            'needs_verification' => $needed,
        ];
    }

    /**
     * The customer's own return rate for an order, or NULL when the number has
     * no report behind it.
     *
     * The same expression CxRtsRateSort and RiskScoreSort rank on, so an order
     * this card counts is one the RTS pages show at the same rate. `latest` is
     * the report as it stands now; the `initial` row beside it is what the
     * number looked like when the order came in.
     */
    private const CX_RTS_SQL = "(
        SELECT SUM(r.order_fail) / NULLIF(SUM(r.order_fail) + SUM(r.order_success), 0)
        FROM pancake_order_phone_number_reports r
        WHERE r.order_id = po.id AND r.type = 'latest'
    )";

    /** At or above this customer return rate, an order is worth ringing first. */
    private const VERIFICATION_RTS_THRESHOLD = 0.55;

    /**
     * Orders confirmed in a range that are worth a verification call.
     *
     * Two reasons qualify, and they cannot overlap: the customer's number has
     * no report at all — nothing is known about them — or it has one and the
     * return rate on it is at or above the threshold. Everything else is a
     * customer with a record of taking delivery.
     *
     * Counted on `confirmed_at`, so the card is the work the range created:
     * verification is what happens between a CSR confirming an order and the
     * parcel going out. Orders never confirmed have no date to fall in and are
     * out of it entirely.
     *
     * @return array{orders: int, no_report: int, high_rts: int, needs_verification: int}
     */
    private function verificationBacklog(Workspace $workspace, string $from, string $to): array
    {
        $orders = $this->scopeToVisibleShops(
            DB::table('pancake_orders as po')
                ->where('po.workspace_id', $workspace->id)
                ->whereBetween(DB::raw('DATE(po.confirmed_at)'), [$from, $to])
                ->selectRaw(self::CX_RTS_SQL.' as cx_rts'),
            $workspace,
            'po.shop_id',
        );

        // Wrapped rather than repeated in the SELECT: the rate is a correlated
        // subquery, and both tests would run it once each per order.
        $row = DB::query()
            ->fromSub($orders, 'o')
            ->selectRaw('
                COUNT(*)                  as orders,
                SUM(o.cx_rts IS NULL)     as no_report,
                SUM(o.cx_rts >= ?)        as high_rts
            ', [self::VERIFICATION_RTS_THRESHOLD])
            ->first();

        $noReport = (int) ($row->no_report ?? 0);
        $highRts = (int) ($row->high_rts ?? 0);

        return [
            'orders' => (int) ($row->orders ?? 0),
            'no_report' => $noReport,
            'high_rts' => $highRts,
            'needs_verification' => $noReport + $highRts,
        ];
    }

    /**
     * RMO calls over a range — the call report's own `total_rmo_called`, and
     * the time spent on them.
     *
     * The other side of the split from verificationTotals(): a call stamped to
     * a delivery, so the CSR was chasing a parcel rather than confirming an
     * order. Together the two make up `total_called`. The rollup is nightly, so
     * a range the sync has not reached is zero on both.
     *
     * @return array{calls: int, seconds: int}
     */
    private function rmoCalledTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = $this->callReport($workspace, $from, $to)
            ->selectRaw('
                COALESCE(SUM(total_rmo_called), 0)    as calls,
                COALESCE(SUM(total_rmo_call_time), 0) as seconds
            ')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'seconds' => (int) ($row->seconds ?? 0),
        ];
    }

    /**
     * RMO calls that turned into a conversation over a range — the call
     * report's own `total_rmo_real_called`, and the RMO calls behind them.
     *
     * A real conversation is an RMO call that lasted past
     * RmoDailyStats::CONNECTED_CALL_MIN_SECONDS; under that it is a hello and a
     * hang-up, which is the cut SyncCsrDailyCallRecord makes. The call count
     * comes back with it because it is what the figure is read against. The
     * rollup is nightly, so a range the sync has not reached is zero on both.
     *
     * @return array{real: int, calls: int}
     */
    private function rmoRealTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = $this->callReport($workspace, $from, $to)
            ->selectRaw('
                COALESCE(SUM(total_rmo_real_called), 0) as real_conversations,
                COALESCE(SUM(total_rmo_called), 0)      as calls
            ')
            ->first();

        return [
            'real' => (int) ($row->real_conversations ?? 0),
            'calls' => (int) ($row->calls ?? 0),
        ];
    }

    /**
     * Order-verification calls over a range — the call report's own
     * `total_verification_called`, and the time spent on them.
     *
     * A verification call is one placed against an order with no delivery
     * behind it: the CSR ringing to confirm the order rather than to chase the
     * parcel, which is the split SyncCsrDailyCallRecord makes on
     * `order_for_delivery_id`. The rollup is nightly, so a range the sync has
     * not reached is zero on both.
     *
     * `orders` is the same work counted by order rather than by call — the
     * rollup's own `total_verified_orders`, distinct within a CSR's day on a
     * shop. An order rung three times is three calls and one order.
     *
     * @return array{calls: int, seconds: int, orders: int}
     */
    private function verificationTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = $this->callReport($workspace, $from, $to)
            ->selectRaw('
                COALESCE(SUM(total_verification_called), 0)    as calls,
                COALESCE(SUM(total_verification_call_time), 0) as seconds,
                COALESCE(SUM(total_verified_orders), 0)        as orders
            ')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'seconds' => (int) ($row->seconds ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    /**
     * Calls placed and time spent on them over a range — the call report's own
     * `total_called` and `total_call_time`.
     *
     * Every call against an order, RMO work and order verification alike,
     * which is what those two columns count; the `total_rmo_*` pair beside
     * them is the narrower figure the other call cards read. The rollup is
     * nightly, so a range the sync has not reached is zero on both.
     *
     * @return array{calls: int, seconds: int}
     */
    private function callTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = $this->callReport($workspace, $from, $to)
            ->selectRaw('
                COALESCE(SUM(total_called), 0)    as calls,
                COALESCE(SUM(total_call_time), 0) as seconds
            ')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'seconds' => (int) ($row->seconds ?? 0),
        ];
    }

    /**
     * The nightly call report over a range, narrowed to what the viewer may see.
     *
     * Every call figure on the page reads through here, so the team filter is
     * applied once rather than remembered at a dozen call sites.
     */
    private function callReport(Workspace $workspace, string $from, string $to)
    {
        return $this->scopeToVisibleShops(
            DB::table('pancake_user_daily_call_reports')
                ->where('workspace_id', $workspace->id)
                ->whereBetween('date', [$from, $to]),
            $workspace,
        );
    }

    /**
     * The raw call log over a range, narrowed to what the viewer may see.
     *
     * What the nightly rollup is built from, for the one chart that needs
     * something the rollup threw away — the hour a call was placed. The order
     * is joined for the same reason the sync joins it: it is what puts the call
     * in a shop, which is both how the team filter bites and why a call
     * matched to no order is not counted at all.
     */
    private function callLogs(Workspace $workspace, string $from, string $to)
    {
        return $this->scopeToVisibleShops(
            DB::table('call_logs as cl')
                ->join('pancake_orders as po', 'po.id', '=', 'cl.order_id')
                ->where('cl.workspace_id', $workspace->id)
                ->whereBetween('cl.call_date', [$from, $to]),
            $workspace,
            'po.shop_id',
        );
    }

    /**
     * Narrow a shop-keyed query to the shops the viewer's team can see.
     *
     * The rollups are keyed by shop, and a shop belongs to teams — the same path
     * Order and Page take through ScopesToVisibleTeams. Unrestricted viewers
     * with no team picked are left alone; a scoped viewer with no team at all
     * sees nothing, which is the fail-closed the trait uses.
     */
    private function scopeToVisibleShops($query, Workspace $workspace, string $column = 'shop_id')
    {
        $shopIds = $this->visibleShopIds($workspace);

        if ($shopIds === null) {
            return $query;
        }

        return $query->whereIn($column, $shopIds);
    }

    /**
     * The shop ids the viewer's team can see, or null for "no restriction".
     *
     * The order-based cards go through WorkspaceMetrics, which takes shop ids
     * as a filter rather than a query to narrow.
     *
     * @return array<int, int>|null
     */
    private function visibleShopIds(Workspace $workspace): ?array
    {
        return TeamVisibility::scopeShopIds(request()->user(), $workspace);
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
        $perCsr = $this->scopeToVisibleShops(
            DB::table('pancake_user_pos_daily_reports as r')
                ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
                ->where('r.workspace_id', $workspace->id)
                ->whereBetween('r.date', [$from, $to]),
            $workspace,
            'r.shop_id',
        )
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

        // Who is in the running and what their rate reads are two questions.
        // A CSR earns a place by having at least one shop-day that saw both a
        // return and a delivery — half a parcel's story is no evidence of a
        // rate. The rate itself is then read off everything they settled in
        // the range, delivery-only days included, which is the arithmetic and
        // the row set of the RTS Rate column in the table below.
        $perCsr = $this->scopeToVisibleShops(
            DB::table('pancake_user_pos_daily_reports as r')
                ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
                ->where('r.workspace_id', $workspace->id)
                ->whereBetween('r.date', [$from, $to]),
            $workspace,
            'r.shop_id',
        )
            ->groupBy('pu.id', 'pu.name')
            // Suffixed aliases on purpose: an alias of `delivered` shadows
            // r.delivered in the ORDER BY, which ONLY_FULL_GROUP_BY rejects.
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.returning), 0) as returned_amount,
                COALESCE(SUM(r.delivered), 0) as delivered_amount,
                COALESCE(SUM(r.returning_count + r.delivered_count), 0) as settled_orders,
                SUM(CASE WHEN r.returning > 0 AND r.delivered > 0 THEN 1 ELSE 0 END) as qualifying_days
            ')
            // The qualifying day is the entry ticket, not the figure: one of
            // them puts the CSR in the ranking, and the sums above then speak
            // for the whole range. It also keeps both sums off zero, so the
            // rate below always has something to divide.
            ->havingRaw('qualifying_days > 0')
            ->orderByRaw('returned_amount / (returned_amount + delivered_amount) ASC')
            // Ties are common at a clean 0%; break them on the money settled.
            // Not on settled_orders: those counts read zero on every rollup row
            // written before the 2026_09_03 migration added them, so until a
            // `sync:csr-daily-records` backfill lands they break nothing.
            ->orderByRaw('returned_amount + delivered_amount DESC')
            ->get();

        // Money back over money settled, to the decimal the card prints. The
        // qualifying day above keeps the divisor off zero.
        $rate = fn ($row) => round(
            (float) $row->returned_amount
                / ((float) $row->returned_amount + (float) $row->delivered_amount)
                * 100,
            1,
        );

        // Every rate on the board prints as 0.0% — returns too small against the
        // deliveries beside them to show at one decimal. Nothing separates the
        // CSRs and the winner would be whoever the tiebreak reached first, so
        // the card says nobody to rank instead of picking one of them.
        $leader = $perCsr->max($rate) > 0 ? $perCsr->first() : null;

        if ($leader === null) {
            return response()->json(['leader' => null]);
        }

        return response()->json([
            'leader' => [
                'name' => $leader->name,
                'value' => $rate($leader),
                'returned' => (float) $leader->returned_amount,
                'delivered' => (float) $leader->delivered_amount,
                'orders' => (int) $leader->settled_orders,
            ],
        ]);
    }

    public function analyticsLeaderRmoCalled(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        // The CSR table's own RMO % — total_called (deliveries assigned to them
        // that moved off PENDING) over total_confirmed (deliveries they confirmed).
        $leader = $this->scopeToVisibleShops(
            DB::table('pancake_user_daily_call_reports as r')
                ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
                ->where('r.workspace_id', $workspace->id)
                ->whereBetween('r.date', [$from, $to]),
            $workspace,
            'r.shop_id',
        )
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.total_rmo_assigned_count), 0) as called,
                COALESCE(SUM(r.total_rmo_confirmed_count), 0) as confirmed
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
        $leader = $this->scopeToVisibleShops(
            DB::table('pancake_user_daily_call_reports as r')
                ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
                ->where('r.workspace_id', $workspace->id)
                ->whereBetween('r.date', [$from, $to]),
            $workspace,
            'r.shop_id',
        )
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(r.total_rmo_call_time), 0) as seconds,
                COALESCE(SUM(r.total_rmo_called), 0) as calls
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
     * Everything the Sales and RTS cards need, over one date range, off the
     * nightly POS rollup.
     *
     * The cards are the sum of the CSR rows the page already shows: the
     * leaders, the comparison and the breakdown table all read
     * pancake_user_pos_daily_reports, so the totals at the top agree with the
     * names under them. `returning` and `delivered` are money, as on the RTS
     * leader — the parcel counts beside them are only backfilled from
     * 2026-09-03 on, so a rate built from them would be wrong on older rows.
     *
     * `rts_rate` is the rollup's own column, averaged over the rows that
     * actually settled something — a CSR-shop-day with no parcels yet has no
     * rate to contribute, and letting its stored 0 in would read as a period
     * with fewer returns than it had. The mean of the days is not the rate of
     * the whole range, so this figure is a shade off the amount-weighted one
     * the leader and the table compute from `returning` / `delivered`.
     *
     * The rollup is written nightly, so a range the sync has not reached reads
     * as nothing rather than as the dashboard's order figures. Rows written
     * before SyncCsrDailyRecord started storing the rate carry 0.00, and there
     * is no telling those from a genuine 0% here — `sync:csr-daily-records
     * --days=N` rewrites them.
     *
     * @return array{sales: float, orders: int, returning: float, rts_rate: float|null}
     */
    private function posTotals(Workspace $workspace, string $from, string $to): array
    {
        $row = $this->scopeToVisibleShops(
            DB::table((new PancakeUserPosDailyReport)->getTable())
                ->where('workspace_id', $workspace->id)
                ->whereBetween('date', [$from, $to]),
            $workspace,
        )
            ->selectRaw('
                COALESCE(SUM(total_sales), 0)  as sales,
                COALESCE(SUM(total_orders), 0) as orders,
                COALESCE(SUM(`returning`), 0)  as returning_amount,
                COALESCE(SUM(delivered), 0)    as delivered_amount
            ')
            ->first();

        $returning = (float) ($row->returning_amount ?? 0);
        $delivered = (float) ($row->delivered_amount ?? 0);
        $settled = $returning + $delivered;

        return [
            'sales' => (float) ($row->sales ?? 0),
            'orders' => (int) ($row->orders ?? 0),
            'returning' => $returning,
            // Returning over everything settled across the whole range, not the
            // average of the rollup's per-shop-per-day rates: a shop-day with
            // two parcels weighed as much as one with two hundred. The same
            // arithmetic the RTS Rate column, the leader card and the comparison
            // tab already use. Null, not zero, when nothing settled in the
            // range — a rate needs something to divide.
            'rts_rate' => $settled > 0 ? $returning / $settled * 100 : null,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | CSR comparison
     |--------------------------------------------------------------------------
     |
     | The field behind the leaders: every CSR on one axis, against the period's
     | average and their own previous figure. One endpoint, asked for the one
     | metric the panel is showing — `?metric=` — so the scan reads that
     | metric's columns off one rollup instead of every column of both.
     |
     | What can be asked for is CsrComparisonMetrics: every figure column of the
     | two rollups, plus the two rates that cannot be summed out of them.
     */

    /** Chart hues available per CSR — see the panel's BAR_COLORS. */
    private const COMPARISON_COLOURS = 8;

    public function analyticsComparison(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        // The figure the panel is showing. An unknown key — an old link, a
        // hand-edited URL — reads as total sales rather than as an error, the
        // same fallback the page itself applies.
        $metric = CsrComparisonMetrics::find(
            CsrComparisonMetrics::resolveKey($request->input('metric')),
        );

        $rows = $this->comparisonFigures(
            $workspace,
            CsrComparisonMetrics::table($metric['source']),
            CsrComparisonMetrics::columnsFor($metric),
            $from,
            $to,
            $previousFrom,
            $previousTo,
        );

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'previous_period' => ['from' => $previousFrom, 'to' => $previousTo],
            'metric' => $this->comparisonMetric($metric, $rows),
        ]);
    }

    /** A percentage to one decimal; null when there is nothing to divide by. */
    private function rate(float|int|string $part, float|int|string $whole): ?float
    {
        return (float) $whole > 0 ? round((float) $part / (float) $whole * 100, 1) : 0.0;
    }

    /**
     * One metric's figures, per CSR, for both periods in one scan.
     *
     * Off the same rollups as the leader cards, so the field and the winner
     * agree. The periods are contiguous, so one indexed range covers both and
     * the conditional aggregates split them apart; an uncovered range is empty.
     *
     * Column names and aggregates are interpolated into the SQL, so they come
     * from the metric catalogue's literals and never from the request.
     *
     * @param  array<string, string>  $columns  `column => aggregate`
     */
    private function comparisonFigures(
        Workspace $workspace,
        string $table,
        array $columns,
        string $from,
        string $to,
        string $previousFrom,
        string $previousTo,
    ) {
        $selects = ['pu.id as id', 'pu.name as name'];
        $bindings = [];
        $periods = ['current' => [$from, $to], 'previous' => [$previousFrom, $previousTo]];

        foreach ($columns as $column => $aggregate) {
            foreach ($periods as $period => [$start, $end]) {
                $selects[] = "COALESCE({$aggregate}(CASE WHEN r.date BETWEEN ? AND ? THEN r.`{$column}` END), 0) as {$period}_{$column}";
                array_push($bindings, $start, $end);
            }
        }

        return $this->scopeToVisibleShops(
            DB::table("{$table} as r")
                ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
                ->where('r.workspace_id', $workspace->id)
                ->whereBetween('r.date', [$previousFrom, $to]),
            $workspace,
            'r.shop_id',
        )
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->get();
    }

    /**
     * One metric block: every CSR who qualifies, ranked, with the period's
     * average. Not a top few — the whole field is listed, so a CSR with figures
     * is never missing from the chart their figures belong on; the panel scrolls
     * a long roster rather than cutting it off. `total` is that count, which the
     * average is taken over.
     *
     * @param  array<string, mixed>  $metric  One entry of the metric catalogue.
     */
    private function comparisonMetric(array $metric, $rows): array
    {
        $eligible = $rows
            ->map(fn ($row) => [
                // pancake_users.id is a UUID — casting it to an int lands every
                // CSR on 0, quietly merging them.
                'id' => (string) $row->id,
                'name' => $row->name,
                'value' => $this->comparisonValue($metric, $row, 'current'),
                'previous_value' => $this->comparisonValue($metric, $row, 'previous'),
            ])
            ->filter(fn ($row) => $row['value'] !== null)
            ->values();

        $ranked = ($metric['higher_is_better']
            ? $eligible->sortByDesc('value')
            : $eligible->sortBy('value')
        )->values();

        return [
            'key' => $metric['key'],
            'label' => $metric['label'],
            // The heading this metric sits under in the panel's selector.
            'group' => $metric['group'],
            'format' => $metric['format'],
            'delta_unit' => $metric['delta_unit'],
            'higher_is_better' => $metric['higher_is_better'],
            'average' => $eligible->isNotEmpty() ? round($eligible->avg('value'), 2) : null,
            'total' => $eligible->count(),
            'rows' => $ranked->map(fn ($row) => [
                ...$row,
                'change' => $this->comparisonChange($row['value'], $row['previous_value'], $metric['delta_unit']),
                'color_slot' => $this->comparisonColourSlot($row['id']),
            ])->all(),
        ];
    }

    /**
     * One CSR's figure for a metric in one of the two periods, or null when the
     * period gives them nothing to plot — no sales, no parcel settled, no call.
     *
     * Null rather than zero: it keeps them off the axis and out of the average,
     * which is what "the average of the CSRs who did this" has to mean. A rate
     * with nothing under the line is no rate at all, not a zero one.
     */
    private function comparisonValue(array $metric, $row, string $period): ?float
    {
        if (isset($metric['column'])) {
            $value = (float) $row->{"{$period}_{$metric['column']}"};

            return $value > 0 ? $value : null;
        }

        $whole = array_sum(array_map(
            fn (string $column) => (float) $row->{"{$period}_{$column}"},
            $metric['rate']['whole'],
        ));

        return $whole > 0
            ? $this->rate((float) $row->{"{$period}_{$metric['rate']['part']}"}, $whole)
            : null;
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
     * A fixed chart colour per CSR, taken from who they are rather than where
     * they rank, so changing the metric moves the bars without repainting them
     * — the request that draws the next metric never sees the last one's
     * ordering. Past eight CSRs the slots wrap; the name carries identity.
     */
    private function comparisonColourSlot(string $id): int
    {
        return crc32($id) % self::COMPARISON_COLOURS;
    }

    /**
     * Run both CSR rollups now, for today and yesterday only, scoped to this
     * workspace. Local and the test server only.
     *
     * The commands only queue the aggregation jobs, so this returns as soon as
     * they are dispatched — the figures move once the queue drains. Passing
     * --date per day rather than --days is what keeps today in the window: the
     * commands' own backfill starts at yesterday and works backwards.
     */
    public function runSync(Request $request, Workspace $workspace)
    {
        // Local and the test server only. Production keeps to the schedule, and
        // hiding the button there is not on its own a guard.
        abort_if(app()->environment('production'), 403, 'Manual CSR sync is disabled in production.');

        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        $dates = [
            CarbonImmutable::today()->toDateString(),
            CarbonImmutable::yesterday()->toDateString(),
        ];

        $output = [];

        foreach (self::SYNC_COMMANDS as $command) {
            foreach ($dates as $date) {
                Artisan::call($command, [
                    '--workspace' => $workspace->slug,
                    '--date' => $date,
                ]);

                $output[] = trim(Artisan::output());
            }
        }

        return response()->json([
            'commands' => self::SYNC_COMMANDS,
            'dates' => $dates,
            'output' => $output,
        ]);
    }
}

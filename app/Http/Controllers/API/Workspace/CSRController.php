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

    public function analyticsCallsPlaced(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $current = $this->rmoCallTotals($workspace, $from, $to);
        $previous = $this->rmoCallTotals($workspace, $previousFrom, $previousTo);

        return response()->json([
            // Every call placed against an order, however short. A two-second
            // call is still a CSR picking up the phone, and this card counts
            // the picking up.
            'value' => $current['calls'],
            // The subset that actually connected, carried alongside so the
            // card can say how many of the attempts landed.
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
            // What share of the attempts turned into an actual conversation —
            // the figure the count is meaningless without, since 40 out of 60
            // and 40 out of 4,000 are not the same day's work.
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
            // Null, not zero, with no attempts at all: 0% would read as "rang
            // all day and reached nobody" rather than "nobody rang".
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

        // The day it happened, for the footnote. A second, tiny query rather
        // than a window function: MAX() gives the length, not the row it came
        // from, and this is one indexed lookup on a range already scanned.
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
     * The two call cards above give the period's totals; this is the same two
     * figures laid out across the days that made them, so a week where the
     * effort held up but the conversations fell away shows as the gap between
     * the bars widening rather than as a single softened percentage.
     *
     * Same source and same rules as the cards — RMO calls only (an order_id on
     * the row), `real` at the shared five-second threshold — so a day here and
     * the card above it always agree.
     *
     * Every day in the range is returned, including the ones with no calls at
     * all: a Sunday nobody worked is a gap in the run of bars, and dropping it
     * would quietly close that gap up.
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
            // call_date is a DATE column, but drivers hand it back as a string
            // with or without a time part depending on the connection — key on
            // the first ten characters so both shapes land on the same day.
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
     * The chart draws two of these figures; this is the whole split, and it is
     * its own endpoint rather than more columns on the chart's: the two answer
     * different questions, and a table that grows a column later should not
     * make the chart refetch.
     *
     * A day's attempts fall into three buckets, narrowing in turn:
     *
     *   no_answer      the phone never joined — it rang out, or the line was busy
     *   answered       somebody picked up, however briefly
     *   conversations  the answered calls past the shared five-second threshold
     *
     * So calls = no_answer + answered, and conversations is a cut of answered —
     * the gap between them is the pick-up-and-hang-up, effort that got through
     * without becoming anything.
     *
     * `hit_rate` is conversations over every attempt, the same reach rate the
     * stat card at the top of the page reports, per day. Null on a day with no
     * calls at all: 0% would read as "rang all day and reached nobody".
     *
     * Same source and rules as the call cards — RMO calls only, meaning a call
     * carrying an order_id — so a row here and a card above it always agree.
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
            // call_date is a DATE column, but drivers hand it back with or
            // without a time part depending on the connection — key on the
            // first ten characters so both shapes land on the same day.
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

            // Every day in the range gets a row, quiet ones included — the
            // table is a record of the period, not only of the busy days.
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
                // The period's own rate, not the mean of the daily ones — a
                // twenty-call day and a two-call day do not weigh the same.
                'hit_rate' => $calls > 0 ? round($conversations / $calls * 100, 1) : null,
            ],
        ]);
    }

    /**
     * Time spent on RMO calls across a range, and how many calls made it up.
     *
     * Only calls carrying an order_id — the ones matched to a delivery when they
     * synced. A CSR rings numbers all day that belong to no RMO order, and this
     * card is about RMO time, so those are left out.
     *
     * The match is stamped at sync time by CallLogPersona and there is no
     * backfill, so a call logged before order_id existed has none and is not
     * counted. That makes the card cover calls synced from that point on rather
     * than restate history from a match made long after the fact.
     *
     * `connected` is the subset that actually connected — any talk time at all.
     * A zero-second row is a call that never joined: it rang out, or the line
     * was busy, and nobody was reached.
     *
     * `real` is the stricter cut: five seconds or more, the same threshold the
     * RMO page draws, kept on RmoDailyStats so the two cannot drift apart.
     * Under that it is a hello and a hang-up, not a conversation.
     *
     * So the three narrow in turn — every attempt, the ones that joined, the
     * ones where something was actually said.
     *
     * `longest` is the single longest call in the range — the one figure here
     * that is not a total, and the reason the aggregate carries a MAX.
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

    /*
     |--------------------------------------------------------------------------
     | Leaders for the period
     |--------------------------------------------------------------------------
     |
     | Who came top, rather than what the workspace did. Same source and same
     | rules as the stat cards above, so a leader's share of the total is a share
     | of the number the Sales card actually shows.
     */

    public function analyticsLeaderSales(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $perCsr = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->where('po.workspace_id', $workspace->id)
            // Every status, cancellations included — the same rule
            // SyncCsrDailyRecord uses, so a CSR's figure here is the one the
            // table below the cards shows for them.
            //
            // Note this is NOT the Sales card's rule: that follows the dashboard
            // and drops statuses 6 and 7. The two will differ by the value of
            // the period's cancelled orders, deliberately — the leaderboard
            // credits work done, the Sales card reports money kept.
            ->whereBetween('po.confirmed_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.id as pancake_user_id,
                pu.name as name,
                COALESCE(SUM(po.final_amount), 0) as sales,
                COUNT(*) as orders
            ')
            ->orderByDesc('sales')
            ->get();

        $leader = $perCsr->first();

        if ($leader === null) {
            return response()->json(['leader' => null]);
        }

        // The total is the CSRs' own total, summed across this same breakdown —
        // not the workspace's. An order whose confirmed_by resolves to nobody
        // counts on the Sales card but belongs to no CSR, and dividing by a
        // total that includes it would leave every share short of the truth.
        // Measured this way the shares add up to 100%.
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
                // Their slice of everything the CSRs confirmed between them.
                // Null rather than 100% when that total is somehow zero — a
                // share of nothing is not a share.
                'share' => $total > 0 ? round($sales / $total * 100, 1) : null,
            ],
        ]);
    }

    public function analyticsLeaderRts(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewCsrAnalytics->value, $workspace);

        [$from, $to] = $this->range($request);

        $start = $from.' 00:00:00';
        $end = $to.' 23:59:59';

        // The rollup's own definitions, so a CSR's rate here is the one the RTS
        // Rate column shows for them: money that turned back over money that
        // arrived, each counted on the day it happened rather than the day the
        // order was confirmed.
        $perCsr = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->where('po.workspace_id', $workspace->id)
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.name as name,
                COALESCE(SUM(CASE WHEN po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END), 0) as returned,
                COALESCE(SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END), 0) as delivered,
                COUNT(CASE
                    WHEN (po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ?)
                      OR (po.status = 3 AND po.delivered_at BETWEEN ? AND ?)
                    THEN 1
                END) as settled_orders
            ', [$start, $end, $start, $end, $start, $end, $start, $end])
            // Eligibility is one settled parcel, counted — not one peso. A
            // delivery worth nothing is still a delivery, and summing amounts
            // would have quietly dropped whoever handled it.
            ->havingRaw('settled_orders >= 1')
            // NULL sorts first in MySQL, so a CSR whose settled parcels are all
            // worth zero — an undefined rate — would otherwise take the crown.
            // Push those to the back and let a real rate win.
            ->orderByRaw('(returned / NULLIF(returned + delivered, 0)) IS NULL')
            ->orderByRaw('returned / NULLIF(returned + delivered, 0) ASC')
            // Ties are common at a clean 0%. Break them on volume, so the
            // winner is whoever managed it over more parcels — and so the same
            // data always crowns the same person rather than whichever row the
            // database happened to return first.
            ->orderByDesc('settled_orders')
            ->first();

        if ($perCsr === null) {
            return response()->json(['leader' => null]);
        }

        $returned = (float) $perCsr->returned;
        $delivered = (float) $perCsr->delivered;
        $settled = $returned + $delivered;

        return response()->json([
            'leader' => [
                'name' => $perCsr->name,
                // Zero when their settled parcels are all worth nothing: they
                // are eligible on the count, and none of it came back.
                'value' => $settled > 0 ? round($returned / $settled * 100, 1) : 0.0,
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

        // Read off the same rollup the CSR table reads, using the table's own
        // RMO % — total_called over total_confirmed — so the leader's figure is
        // the number in that CSR's row rather than a second opinion on it.
        //
        // Note those two are different roles: total_called counts deliveries
        // assigned to them that moved off PENDING, total_confirmed counts
        // deliveries they confirmed. The table divides one by the other, and
        // this follows it deliberately.
        //
        // Consequence worth knowing: because the rollup is written nightly, a
        // range the sync has not covered has no leader here, where the stat
        // cards above would still have figures.
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
            // Ties break on volume, so the same data always crowns the same
            // person rather than whichever row came back first.
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

        // Off the same rollup the CSR table reads, so this is that CSR's "RMO
        // Call Time" column summed over the range, and the average uses the
        // call count sitting beside it in "RMO Called".
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
            // No time on the phone is not "most time on calls". Without this the
            // card would crown somebody at 00:00 on a quiet week.
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
                // Null when the rollup recorded time but no calls to divide it
                // between — the two columns are written independently, so that
                // is possible and an average of nothing is not zero.
                'average_seconds' => $calls > 0 ? round($seconds / $calls, 1) : null,
            ],
        ]);
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

    /*
     |--------------------------------------------------------------------------
     | CSR comparison
     |--------------------------------------------------------------------------
     |
     | The leaders above name one winner per metric. This is the field behind
     | them: every CSR on the same axis, against the period's average and
     | against their own previous-period figure.
     |
     | One endpoint, all four metrics. They come from two scans — the orders
     | table and the RMO rollup — and each scan already carries everything both
     | of its metrics need, so splitting this into four requests would repeat
     | the same two queries twice over. Switching tabs is then instant, and
     | costs nothing.
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
                // Eligibility is one settled parcel, counted — the rule the RTS
                // leader card uses. A parcel worth nothing is still a parcel.
                fn ($r) => $r->settled_orders >= 1 ? $this->rate($r->returned, $r->returned + $r->delivered) : null,
                fn ($r) => $r->previous_settled_orders >= 1 ? $this->rate($r->previous_returned, $r->previous_returned + $r->previous_delivered) : null,
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
     * The two periods are contiguous, so a single WHERE over the whole span
     * bounds the scan; the conditional sums split it back apart. Same rules as
     * the leader cards: sales are every order the CSR confirmed in the window,
     * cancellations included, and RTS counts money on the day it settled rather
     * than the day the order was taken.
     */
    private function comparisonOrderFigures(Workspace $workspace, string $from, string $to, string $previousFrom, string $previousTo)
    {
        $span = [$previousFrom.' 00:00:00', $to.' 23:59:59'];
        $now = [$from.' 00:00:00', $to.' 23:59:59'];
        $before = [$previousFrom.' 00:00:00', $previousTo.' 23:59:59'];

        return DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->where('po.workspace_id', $workspace->id)
            // Any row that feeds either period has one of these three stamps
            // inside the span. Without it this reads the workspace's whole
            // order history to answer a question about two weeks of it.
            ->where(function ($q) use ($span) {
                $q->whereBetween('po.confirmed_at', $span)
                    ->orWhereBetween('po.returning_at', $span)
                    ->orWhereBetween('po.delivered_at', $span);
            })
            ->groupBy('pu.id', 'pu.name')
            ->selectRaw('
                pu.id as id,
                pu.name as name,
                COALESCE(SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN po.final_amount END), 0) as sales,
                COALESCE(SUM(CASE WHEN po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount END), 0) as returned,
                COALESCE(SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount END), 0) as delivered,
                COUNT(CASE
                    WHEN (po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ?)
                      OR (po.status = 3 AND po.delivered_at BETWEEN ? AND ?)
                    THEN 1
                END) as settled_orders,
                COALESCE(SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN po.final_amount END), 0) as previous_sales,
                COALESCE(SUM(CASE WHEN po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount END), 0) as previous_returned,
                COALESCE(SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount END), 0) as previous_delivered,
                COUNT(CASE
                    WHEN (po.status IN (4, 5) AND po.returning_at BETWEEN ? AND ?)
                      OR (po.status = 3 AND po.delivered_at BETWEEN ? AND ?)
                    THEN 1
                END) as previous_settled_orders
            ', [
                ...$now, ...$now, ...$now, ...$now, ...$now,
                ...$before, ...$before, ...$before, ...$before, ...$before,
            ])
            ->get();
    }

    /**
     * RMO % and talk time per CSR, both periods, off the nightly rollup — the
     * same source the CSR table and the two RMO leader cards read, so a row
     * here is that CSR's row there.
     *
     * Consequence worth knowing: a range the sync has not covered yet is empty
     * on these two metrics, where the order-based ones still have figures.
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
     * One metric block: the CSRs who qualify, ranked, with the period's average
     * to draw them against.
     *
     * The average is over everyone who qualified, not over the listed rows —
     * a top eight measured against its own mean would put half the field above
     * average by construction.
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
                // pancake_users.id is a UUID — keep it a string. Casting it to
                // an int lands every CSR on 0, which quietly merges them.
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
            // Percentage points for the metrics that are already rates: 12% to
            // 15% is "+3 pts", not "+25%". The stat cards above report the same
            // two that way.
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
     * This period against the one before, in the metric's own unit.
     *
     * Null rather than zero when there is nothing to compare against: a CSR's
     * first period has no previous figure, and 0 there would read as "flat".
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
     * A fixed chart colour per CSR, assigned once across all four metrics.
     *
     * Keyed to the person, not to their rank: the sales ranking picks the
     * order, and every other tab reuses it, so flipping tabs moves the bars
     * without repainting them. Past eight distinct CSRs the slots wrap — the
     * name label carries identity, the hue only helps the eye track a row.
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

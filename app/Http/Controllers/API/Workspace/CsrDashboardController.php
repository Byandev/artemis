<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Concerns\ResolvesCsrDateRange;
use App\Http\Controllers\Controller;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Workspace;
use App\Support\RiskyOrders;
use App\Support\RmoDailyStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR dashboard's stat cards — the signed-in CSR's own figures.
 *
 * The analytics endpoints next door report the workspace; these report one
 * person. Same nightly rollups, same arithmetic and the same shape of answer
 * (`value`, the previous period's figure and the move between them), so a
 * CSR's card is their own row of the analytics breakdown and nothing else.
 *
 * Kept apart from CSRController because the two differ in the things that
 * matter most about an endpoint: who may call it, and which rows it may read.
 *
 *  - Access. Analytics is gated on ViewCsrAnalytics, which a CSR does not
 *    have. Membership is the whole check here — the rows are already narrowed
 *    to the caller's own pancake accounts, so there is nothing to withhold.
 *  - Scope. Analytics narrows by team visibility; this narrows by identity.
 *    Team scoping is deliberately NOT applied: it exists to keep one team out
 *    of another's rows, and TeamVisibility::scopeShopIds() fails closed for a
 *    CSR in no team — which would blank their own dashboard rather than
 *    protect anything.
 *
 * Sharing one controller would mean every future card choosing between the two
 * sets of rules at its own call site. The URLs still sit in the
 * /csrs/stats/* family, which is what the frontend's stat-card hook reads.
 *
 * Every card the analytics page shows has a counterpart here. One is built
 * differently: Confirmed Risky Orders, which analytics reads off the nightly
 * page_order_report_breakdown_daily_records rollup. That rollup is bucketed by
 * shop and by the customer history an order arrived with, and carries no CSR
 * column — so this counts the same orders off pancake_orders itself, keyed by
 * who confirmed them. See ownRiskyOrders().
 */
class CsrDashboardController extends Controller
{
    use ResolvesCsrDateRange;

    /**
     * Every figure column the breakdown carries, in the order the table shows
     * them — and the test for whether a day is a row at all.
     *
     * The two rates are deliberately absent: both are computed from figures
     * already on this list, so a day with a rate has the figures behind it too.
     */
    private const BREAKDOWN_FIGURES = [
        'total_orders',
        'total_sales',
        'total_delivered',
        'total_delivered_count',
        'total_returning',
        'total_returning_count',

        'total_confirmed',
        'total_called',
        'total_rmo_call_attempts',
        'total_rmo_orders',
        'total_call_time',
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
        'total_all_called',
        'total_all_call_time',
    ];

    /*
     |--------------------------------------------------------------------------
     | Sales
     |--------------------------------------------------------------------------
     |
     | Off the nightly POS rollup — pancake_user_pos_daily_reports, the same
     | rows the analytics breakdown lists the CSR's name against.
     */

    /** Sales and the orders behind them, over the range and the one before it. */
    public function sales(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->comparePos($request, $workspace);

        return response()->json([
            'value' => $current['sales'],
            'orders' => $current['orders'],
            'previous_value' => $previous['sales'],
            'previous_orders' => $previous['orders'],
            'change' => $this->relativeChange($current['sales'], $previous['sales']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /**
     * The share of the CSR's own settled parcels that came back.
     *
     * Returning over returning plus delivered, summed over the whole range —
     * not the average of the rollup's per-shop-per-day rates, where a day with
     * two parcels would weigh as much as one with two hundred. The same
     * arithmetic the analytics RTS card uses, over one person's rows.
     */
    public function rts(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->comparePos($request, $workspace);

        $rate = $current['rts_rate'];
        $previousRate = $previous['rts_rate'];

        return response()->json([
            // Null, not zero, when nothing in the range settled: a 0% would
            // read as a perfect period rather than an unfinished one.
            'value' => $rate === null ? null : round($rate, 2),
            'returning_amount' => $current['returning'],
            'previous_value' => $previousRate === null ? null : round($previousRate, 2),
            'change' => $this->pointChange($rate, $previousRate),
            'previous_period' => $previousPeriod,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Calls — every kind
     |--------------------------------------------------------------------------
     |
     | `total_called` / `total_call_time` are every call the CSR placed against
     | an order, RMO work and order verification alike. The narrower figures
     | each have their own pair of cards below.
     */

    /** Calls placed, of any kind. */
    public function rmoCalled(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'all');

        return response()->json([
            'value' => $current['calls'],
            'previous_value' => $previous['calls'],
            'change' => $this->relativeChange($current['calls'], $previous['calls']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /** Time spent on those calls. */
    public function rmoTime(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'all');

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            'average_seconds' => $this->average($current['seconds'], $current['calls']),
            'previous_value' => $previous['seconds'],
            'change' => $this->relativeChange($current['seconds'], $previous['seconds']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | RMO calls — chasing a parcel
     |--------------------------------------------------------------------------
     |
     | A call stamped to a delivery, which is the split SyncCsrDailyCallRecord
     | makes on `order_for_delivery_id`. RMO rings the same parcel more than
     | once by design, so the calls and the deliveries behind them are far
     | apart and both are reported.
     */

    /** RMO calls placed, and the deliveries they were about. */
    public function totalRmoCalled(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'rmo');

        return response()->json([
            'value' => $current['calls'],
            // The talk time behind them; the card beside this one shows it.
            'seconds' => $current['seconds'],
            // The same calls counted by delivery — a parcel rung three times is
            // three calls and one order.
            'orders' => $current['orders'],
            'previous_value' => $previous['calls'],
            'change' => $this->relativeChange($current['calls'], $previous['calls']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /** Time spent on RMO calls. */
    public function rmoCallTime(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'rmo');

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            'average_seconds' => $this->average($current['seconds'], $current['calls']),
            'previous_value' => $previous['seconds'],
            'change' => $this->relativeChange($current['seconds'], $previous['seconds']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /**
     * RMO calls that turned into a conversation.
     *
     * One that lasted past RmoDailyStats::CONNECTED_CALL_MIN_SECONDS; under
     * that it is a hello and a hang-up, which is the cut the nightly sync
     * makes. The calls behind them come too — that is what the figure is read
     * against.
     */
    public function rmoRealConversations(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'rmo');

        return response()->json([
            'value' => $current['real_conversations'],
            'calls' => $current['calls'],
            'rate' => $this->rate($current['real_conversations'], $current['calls']),
            'previous_value' => $previous['real_conversations'],
            'change' => $this->relativeChange($current['real_conversations'], $previous['real_conversations']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /** The share of the CSR's RMO calls that got through. */
    public function rmoHitRate(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'rmo');

        // Undefined rather than zero with nothing placed: 0% reads as "everyone
        // hung up", which is not "nobody called".
        $rate = $this->rate($current['real_conversations'], $current['calls']);
        $previousRate = $this->rate($previous['real_conversations'], $previous['calls']);

        return response()->json([
            'value' => $rate,
            'conversations' => $current['real_conversations'],
            'calls' => $current['calls'],
            'previous_value' => $previousRate,
            'change' => $this->pointChange($rate, $previousRate),
            'previous_period' => $previousPeriod,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Verification calls — confirming an order
     |--------------------------------------------------------------------------
     |
     | The other side of the split: a call placed against an order with no
     | delivery behind it yet, so the CSR was confirming the order rather than
     | chasing the parcel.
     */

    /** Verification calls placed. */
    public function callsPlaced(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'verification');

        return response()->json([
            'value' => $current['calls'],
            // The same calls counted by order, so the card can say how many
            // orders the ringing actually got through.
            'orders' => $current['orders'],
            'previous_value' => $previous['calls'],
            'change' => $this->relativeChange($current['calls'], $previous['calls']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /** Time spent on verification calls. */
    public function realConversations(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareCalls($request, $workspace, 'verification');

        return response()->json([
            'value' => $current['seconds'],
            'calls' => $current['calls'],
            'average_seconds' => $this->average($current['seconds'], $current['calls']),
            'previous_value' => $previous['seconds'],
            'change' => $this->relativeChange($current['seconds'], $previous['seconds']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /**
     * Orders confirmed by this CSR in the range whose customer was risky.
     *
     * Risky is App\Support\RiskyOrders — no report at all, or one showing at
     * least MIN_ORDERS past orders returning at or above RTS_THRESHOLD. Same
     * two rules the analytics card applies to the workspace, counted here off
     * the orders rather than the nightly rollup, because that rollup has no
     * CSR column to narrow by. See ownRiskyOrders().
     */
    public function confirmedRiskyOrders(Request $request, Workspace $workspace)
    {
        [$current, $previous, $previousPeriod] = $this->compareRisky($request, $workspace);

        return response()->json([
            'value' => $current['risky_orders'],
            // The two reasons, so the card can say which is driving it.
            'no_report' => $current['no_report'],
            'high_rts' => $current['high_rts'],
            'orders' => $current['orders'],
            'previous_value' => $previous['risky_orders'],
            'change' => $this->relativeChange($current['risky_orders'], $previous['risky_orders']),
            'previous_period' => $previousPeriod,
        ]);
    }

    /**
     * How many of the CSR's own risky orders they verified.
     *
     * Orders on both sides of the division, not calls: three calls at one order
     * cover one order, and counting the calls read a two-order backlog rung
     * three times as 150%.
     *
     * Both sides are this CSR's. That is what the card is for, and it is also
     * why it can pass 100%: an order confirmed by one CSR and verified by
     * another counts on each of their cards, on opposite sides of the
     * division. Real signal about who is doing the chasing rather than an
     * error to clamp away.
     */
    public function verifiedOrders(Request $request, Workspace $workspace)
    {
        [$currentCalls, $previousCalls, $previousPeriod] = $this->compareCalls($request, $workspace, 'verification');
        [$currentRisky, $previousRisky] = $this->compareRisky($request, $workspace);

        $rate = $this->rate($currentCalls['orders'], $currentRisky['risky_orders']);
        $previousRate = $this->rate($previousCalls['orders'], $previousRisky['risky_orders']);

        return response()->json([
            'value' => $rate,
            // Both sides of the division, so the card can show its own working,
            // and the calls behind the orders — the effort against the coverage.
            'orders' => $currentCalls['orders'],
            'calls' => $currentCalls['calls'],
            'risky_orders' => $currentRisky['risky_orders'],
            'previous_value' => $previousRate,
            'change' => $this->pointChange($rate, $previousRate),
            'previous_period' => $previousPeriod,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | The breakdown — the CSR's own days
     |--------------------------------------------------------------------------
     |
     | The analytics breakdown lists every figure of both nightly rollups
     | against a CSR's name. This lists the same figures against a date, for
     | one CSR: their own work, day by day. The columns are the same and carry
     | the same aliases, so the two tables are read the same way — only the
     | grain differs.
     |
     | A CSR works more than one shop, and both rollups are split by shop, so a
     | day is the sum of that day's shop rows. Sorting and paging happen here
     | rather than in SQL: a date range is a few hundred rows at the very most,
     | and merging two rollups on a date is work the database cannot do without
     | a full outer join it does not have.
     */

    public function breakdown(Request $request, Workspace $workspace)
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        $days = $this->breakdownDays($workspace, $ids, $from, $to);

        return response()->json($this->paginateDays($request, $days));
    }

    /**
     * One row per day the CSR did something, both rollups merged on the date.
     *
     * Days where nothing moved are left out rather than listed as a row of
     * zeros — the same rule the analytics breakdown applies to a CSR who did
     * not work the range. The effort charts above are where a quiet day still
     * shows, because a gap in a chart is the reading and a gap in a table is
     * noise.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @return array<int, array<string, mixed>>
     */
    private function breakdownDays(Workspace $workspace, array $pancakeUserIds, string $from, string $to): array
    {
        if ($pancakeUserIds === []) {
            return [];
        }

        $pos = $this->breakdownRollup(
            (new PancakeUserPosDailyReport)->getTable(),
            $pancakeUserIds,
            $workspace,
            $from,
            $to,
            '
                COALESCE(SUM(total_orders), 0)     as total_orders,
                COALESCE(SUM(total_sales), 0)      as total_sales,
                COALESCE(SUM(delivered), 0)        as total_delivered,
                COALESCE(SUM(delivered_count), 0)  as total_delivered_count,
                COALESCE(SUM(`returning`), 0)      as total_returning,
                COALESCE(SUM(returning_count), 0)  as total_returning_count
            ',
        );

        // The call report. Aliased to the names the analytics table already
        // sorts on: `total_called` is the RMO assignments, and the report's own
        // `total_called` is `total_all_called`. The longest call is a max, so a
        // day takes the max of its shop rows rather than adding them up.
        $calls = $this->breakdownRollup(
            'pancake_user_daily_call_reports',
            $pancakeUserIds,
            $workspace,
            $from,
            $to,
            '
                COALESCE(SUM(total_rmo_assigned_count), 0)     as total_called,
                COALESCE(SUM(total_rmo_confirmed_count), 0)    as total_confirmed,
                COALESCE(SUM(total_rmo_called), 0)             as total_rmo_call_attempts,
                COALESCE(SUM(total_rmo_orders), 0)             as total_rmo_orders,
                COALESCE(SUM(total_rmo_call_time), 0)          as total_call_time,
                COALESCE(SUM(total_rmo_connected_called), 0)   as total_rmo_connected_called,
                COALESCE(SUM(total_rmo_real_called), 0)        as total_rmo_real_called,
                COALESCE(MAX(longest_rmo_call_time), 0)        as longest_rmo_call_time,
                COALESCE(SUM(total_rmo_customer_called), 0)    as total_rmo_customer_called,
                COALESCE(SUM(total_rmo_customer_call_time), 0) as total_rmo_customer_call_time,
                COALESCE(SUM(total_rmo_rider_called), 0)       as total_rmo_rider_called,
                COALESCE(SUM(total_rmo_rider_call_time), 0)    as total_rmo_rider_call_time,
                COALESCE(SUM(total_verification_called), 0)       as total_verification_called,
                COALESCE(SUM(total_verification_call_time), 0)    as total_verification_call_time,
                COALESCE(SUM(total_verification_real_called), 0)  as total_verification_real_called,
                COALESCE(SUM(total_verified_orders), 0)          as total_verified_orders,
                COALESCE(SUM(total_called), 0)                   as total_all_called,
                COALESCE(SUM(total_call_time), 0)                as total_all_call_time
            ',
        );

        $rows = [];

        foreach (array_unique([...array_keys($pos), ...array_keys($calls)]) as $date) {
            $row = ['date' => $date] + ($pos[$date] ?? []) + ($calls[$date] ?? []);

            // Every column the table can show, so a day present in one rollup
            // and absent from the other reads as zeros rather than as gaps.
            $row += array_fill_keys(self::BREAKDOWN_FIGURES, 0);

            // A day where nothing moved is not a row. The two rates are
            // deliberately not in the test: both are computed from figures
            // already on the list, so a day with a rate has the figures behind
            // it too.
            if (! collect(self::BREAKDOWN_FIGURES)->contains(fn ($figure) => $row[$figure] != 0)) {
                continue;
            }

            $returning = (float) $row['total_returning'];
            $settled = $returning + (float) $row['total_delivered'];
            $confirmed = (int) $row['total_confirmed'];

            $row['rts_rate'] = $settled > 0 ? round($returning / $settled * 100, 2) : 0;
            // RMO % = RMO assigned over RMO confirmed, as in the analytics
            // table. Nothing confirmed is no rate, which the column draws as a
            // dash rather than as a zero.
            $row['rmo_percentage'] = $confirmed > 0
                ? round((int) $row['total_called'] / $confirmed * 100, 2)
                : 0;

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * One nightly rollup summed per date for one set of pancake accounts,
     * keyed by date.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @return array<string, array<string, mixed>>
     */
    private function breakdownRollup(
        string $table,
        array $pancakeUserIds,
        Workspace $workspace,
        string $from,
        string $to,
        string $figures,
    ): array {
        return DB::table($table)
            ->where('workspace_id', $workspace->id)
            ->whereIn('pancake_user_id', $pancakeUserIds)
            ->whereBetween('date', [$from, $to])
            ->groupBy('date')
            ->selectRaw("date, {$figures}")
            ->get()
            // date comes back with or without a time part depending on the
            // driver — key on the first ten characters.
            ->mapWithKeys(fn ($row) => [
                substr((string) $row->date, 0, 10) => collect((array) $row)
                    ->except('date')
                    ->map(fn ($value) => is_numeric($value) ? $value + 0 : $value)
                    ->all(),
            ])
            ->all();
    }

    /**
     * Sort and page the days, and answer in the shape the table's paginator
     * reads — the same envelope Laravel's own paginator produces, so the
     * frontend cannot tell this table from the analytics one.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function paginateDays(Request $request, array $days): array
    {
        // `-column` is descending, as everywhere else Spatie's QueryBuilder
        // takes a sort. An unknown column falls back to the date, newest
        // first, rather than erroring on a hand-edited URL.
        $sort = (string) $request->input('sort', '-date');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if ($column !== 'date' && ! in_array($column, self::BREAKDOWN_FIGURES, true)
            && ! in_array($column, ['rts_rate', 'rmo_percentage'], true)) {
            $column = 'date';
            $descending = true;
        }

        $days = collect($days)
            ->sortBy($column, SORT_REGULAR, $descending)
            ->values();

        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $page = max(1, $request->integer('page', 1));

        return (new LengthAwarePaginator(
            $days->forPage($page, $perPage)->values(),
            $days->count(),
            $perPage,
            $page,
        ))->toArray();
    }

    /*
     |--------------------------------------------------------------------------
     | Effort against results
     |--------------------------------------------------------------------------
     |
     | The two call cards' totals spread across the days that made them, and
     | again across the hours of the day. Narrowed to the CSR's own calls like
     | every other figure on the page, so the shape is their working week
     | rather than the workspace's.
     |
     | Both return the same five figures per bucket: `calls`/`real` are the RMO
     | ones, `verification_calls`/`verification_real` the order-verification
     | ones beside them, and `total_calls` every call the bucket carried. The
     | chart draws one kind or the other and switches client-side, so one
     | request answers all three views.
     */

    /** Day by day, off the nightly call rollup. */
    public function dailyEffort(Request $request, Workspace $workspace)
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        $rows = $ids === []
            ? collect()
            : DB::table('pancake_user_daily_call_reports')
                ->where('workspace_id', $workspace->id)
                ->whereIn('pancake_user_id', $ids)
                ->whereBetween('date', [$from, $to])
                ->groupBy('date')
                ->selectRaw('
                    date,
                    COALESCE(SUM(total_called), 0) as total_calls,
                    COALESCE(SUM(total_rmo_called), 0) as calls,
                    COALESCE(SUM(total_rmo_real_called), 0) as real_conversations,
                    COALESCE(SUM(total_verification_called), 0) as verification_calls,
                    COALESCE(SUM(total_verification_real_called), 0) as verification_real
                ')
                ->get()
                // date comes back with or without a time part depending on the
                // driver — key on the first ten characters.
                ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        // Every day in the range, zeros included: a chart that skipped the
        // quiet days would draw a week the CSR did not work.
        $days = [];
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $days[] = $this->effortBucket($rows->get($date), ['date' => $date]);
            $cursor = $cursor->addDay();
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'totals' => $this->sumEffort($days),
        ]);
    }

    /**
     * The same effort and results, laid across the hours of a day.
     *
     * The chart above reads the nightly rollup, which is keyed to a date and so
     * cannot say when in the day the work happened. This goes back to the call
     * log that rollup is built from and groups by the hour stamped on each
     * call — narrowed by `call_logs.user_id`, which is the pancake account the
     * call was placed by and the same column the nightly sync groups on.
     *
     * The hour alone, not the day and the hour: the range arrives folded into
     * one round of the clock, every day's 9am added into a single 9am. That is
     * the question the hour is asked — when in the day the work lands — and one
     * Tuesday is too small a sample to answer it.
     *
     * The rules are the sync's own, so this adds up to the chart above it: a
     * delivery stamp is what makes a call RMO work rather than order
     * verification, and a conversation is a call that lasted past
     * RmoDailyStats::CONNECTED_CALL_MIN_SECONDS. Reading the log direct does
     * mean this chart covers today, where the daily one is only as fresh as the
     * last rollup.
     */
    public function hourlyEffort(Request $request, Workspace $workspace)
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        $real = RmoDailyStats::CONNECTED_CALL_MIN_SECONDS;

        $rows = $ids === []
            ? collect()
            : DB::table('call_logs as cl')
                // The order join is the sync's own, and it is what keeps this
                // chart adding up to the daily one: a call matched to no order
                // is not counted there, so it is not counted here either.
                ->whereNotNull('cl.order_id')
                ->join('pancake_orders as po', 'po.id', '=', 'cl.order_id')
                ->where('cl.workspace_id', $workspace->id)
                ->whereIn('cl.user_id', $ids)
                ->whereBetween('cl.call_date', [$from, $to])
                ->groupByRaw('HOUR(cl.call_time)')
                ->selectRaw("
                    HOUR(cl.call_time) as hour,

                    COUNT(*) as total_calls,

                    SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL THEN 1 ELSE 0 END) as calls,
                    SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.duration >= {$real} THEN 1 ELSE 0 END) as real_conversations,

                    SUM(CASE WHEN cl.order_for_delivery_id IS NULL THEN 1 ELSE 0 END) as verification_calls,
                    SUM(CASE WHEN cl.order_for_delivery_id IS NULL AND cl.duration >= {$real} THEN 1 ELSE 0 END) as verification_real
                ")
                ->get()
                ->keyBy(fn ($row) => (int) $row->hour);

        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = $this->effortBucket($rows->get($hour), ['hour' => $hour]);
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            // How many days went into each bar. The chart says so — an hour
            // reading 40 over a week is a different figure from 40 in a day.
            'day_count' => (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1,
            'hours' => $hours,
            // The range as a whole, which is the daily chart's totals over the
            // same range — only the grouping differs, never the counting.
            'totals' => $this->sumEffort($hours),
        ]);
    }

    /**
     * One bucket of an effort chart — a day or an hour — read off its row, or
     * zeros where there is no row for it.
     *
     * @param  array<string, int|string>  $key  what names the bucket
     * @return array<string, int|string>
     */
    private function effortBucket(?object $row, array $key): array
    {
        return $key + [
            'total_calls' => (int) ($row->total_calls ?? 0),
            'calls' => (int) ($row->calls ?? 0),
            'real' => (int) ($row->real_conversations ?? 0),
            'verification_calls' => (int) ($row->verification_calls ?? 0),
            'verification_real' => (int) ($row->verification_real ?? 0),
        ];
    }

    /**
     * The effort figures added up over whatever buckets carry them.
     *
     * @param  array<int, array<string, int|string>>  $rows
     * @return array<string, int>
     */
    private function sumEffort(array $rows): array
    {
        return collect(['total_calls', 'calls', 'real', 'verification_calls', 'verification_real'])
            ->mapWithKeys(fn (string $figure) => [$figure => array_sum(array_column($rows, $figure))])
            ->all();
    }

    /*
     |--------------------------------------------------------------------------
     | The two comparisons every card above is built from
     |--------------------------------------------------------------------------
     |
     | Each card measures its range twice — once as itself, once against the
     | equally long stretch ending the day before it, which is what the arrow
     | reports. Resolving the range, the roster and both readings in one place
     | is what keeps a dozen endpoints to a handful of lines each.
     */

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array{from: string, to: string}}
     */
    private function comparePos(Request $request, Workspace $workspace): array
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        return [
            $this->ownPosTotals($workspace, $ids, $from, $to),
            $this->ownPosTotals($workspace, $ids, $previousFrom, $previousTo),
            ['from' => $previousFrom, 'to' => $previousTo],
        ];
    }

    /**
     * @param  'all'|'rmo'|'verification'  $kind
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array{from: string, to: string}}
     */
    private function compareCalls(Request $request, Workspace $workspace, string $kind): array
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        return [
            $this->ownCallTotals($workspace, $ids, $from, $to, $kind),
            $this->ownCallTotals($workspace, $ids, $previousFrom, $previousTo, $kind),
            ['from' => $previousFrom, 'to' => $previousTo],
        ];
    }

    /**
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array{from: string, to: string}}
     */
    private function compareRisky(Request $request, Workspace $workspace): array
    {
        $this->authorizeDashboard($request, $workspace);

        [$from, $to] = $this->range($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $ids = $this->ownPancakeUserIds($request, $workspace);

        return [
            $this->ownRiskyOrders($workspace, $ids, $from, $to),
            $this->ownRiskyOrders($workspace, $ids, $previousFrom, $previousTo),
            ['from' => $previousFrom, 'to' => $previousTo],
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Access, roster and the two rollups
     |--------------------------------------------------------------------------
     */

    /**
     * Gate a dashboard endpoint the way the page itself is gated: the module
     * toggle, so the endpoints disappear with the page, then membership.
     */
    private function authorizeDashboard(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->csr_dashboard_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    /**
     * The pancake accounts the signed-in user is linked to within this
     * workspace — the CSR identities their rollup rows are keyed by.
     *
     * The same link the CSR management table shows (`systemUser`), read from
     * the other side. A user with no linked account gets an empty list, which
     * the totals below read as zeros rather than as "everyone".
     *
     * @return array<int, string>
     */
    private function ownPancakeUserIds(Request $request, Workspace $workspace): array
    {
        return PancakeUser::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('shopUsers.shop', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->pluck('id')
            ->all();
    }

    /**
     * Everything the sales cards need over one range, off the nightly POS
     * rollup — the analytics page's posTotals() narrowed to a person instead
     * of to a team's shops.
     *
     * `returning` and `delivered` are money, as they are there: the parcel
     * counts beside them in the rollup are only backfilled from 2026-09-03 on,
     * so a rate built from those would be wrong on older rows.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @return array{sales: float, orders: int, returning: float, rts_rate: float|null}
     */
    private function ownPosTotals(Workspace $workspace, array $pancakeUserIds, string $from, string $to): array
    {
        if ($pancakeUserIds === []) {
            return ['sales' => 0.0, 'orders' => 0, 'returning' => 0.0, 'rts_rate' => null];
        }

        $row = DB::table((new PancakeUserPosDailyReport)->getTable())
            ->where('workspace_id', $workspace->id)
            ->whereIn('pancake_user_id', $pancakeUserIds)
            ->whereBetween('date', [$from, $to])
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
            // Null, not zero, when nothing settled in the range — a rate needs
            // something to divide.
            'rts_rate' => $settled > 0 ? $returning / $settled * 100 : null,
        ];
    }

    /**
     * Which columns of the nightly call report each kind of card sums.
     *
     * `all` is every call placed against an order; `rmo` and `verification`
     * are the two halves of it, split on whether the call was stamped to a
     * delivery. Only `calls` and `seconds` are read under `all` — the two
     * cards built on it are a count and a duration — so its `orders` and
     * `real_conversations` are the RMO columns for want of an all-calls
     * equivalent, and nothing asks for them.
     *
     * The aliases are the array keys, so none of them may be a MySQL keyword:
     * `real` is one, which is why the conversation column spells itself out.
     */
    private const CALL_COLUMNS = [
        'all' => [
            'calls' => 'total_called',
            'seconds' => 'total_call_time',
            'orders' => 'total_rmo_orders',
            'real_conversations' => 'total_rmo_real_called',
        ],
        'rmo' => [
            'calls' => 'total_rmo_called',
            'seconds' => 'total_rmo_call_time',
            'orders' => 'total_rmo_orders',
            'real_conversations' => 'total_rmo_real_called',
        ],
        'verification' => [
            'calls' => 'total_verification_called',
            'seconds' => 'total_verification_call_time',
            'orders' => 'total_verified_orders',
            'real_conversations' => 'total_verification_real_called',
        ],
    ];

    /**
     * Calls, talk time, the orders behind them and the ones that turned into a
     * conversation, over a range, for one set of pancake accounts.
     *
     * The rollup is nightly, so a range the sync has not reached is zero
     * throughout — the same way the sales cards read zero for a day it has not
     * built yet.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @param  'all'|'rmo'|'verification'  $kind
     * @return array{calls: int, seconds: int, orders: int, real_conversations: int}
     */
    private function ownCallTotals(Workspace $workspace, array $pancakeUserIds, string $from, string $to, string $kind): array
    {
        $empty = ['calls' => 0, 'seconds' => 0, 'orders' => 0, 'real_conversations' => 0];

        if ($pancakeUserIds === []) {
            return $empty;
        }

        $columns = self::CALL_COLUMNS[$kind];

        $row = DB::table('pancake_user_daily_call_reports')
            ->where('workspace_id', $workspace->id)
            ->whereIn('pancake_user_id', $pancakeUserIds)
            ->whereBetween('date', [$from, $to])
            ->selectRaw(collect($columns)
                ->map(fn ($column, $alias) => "COALESCE(SUM({$column}), 0) as {$alias}")
                ->implode(', '))
            ->first();

        return collect($empty)
            ->map(fn ($default, $alias) => (int) ($row->{$alias} ?? $default))
            ->all();
    }

    /**
     * Orders this CSR confirmed in the range, and how many of them were risky.
     *
     * Counted off pancake_orders rather than off
     * page_order_report_breakdown_daily_records: that rollup is bucketed by
     * shop and by customer history and has no CSR column, so there is nothing
     * in it to narrow to one person. `confirmed_by` is that column here — the
     * same one the POS rollup attributes a sale by — and
     * pancake_orders_confirmed_by_confirmed_at_idx is the index for it.
     *
     * The rules are the rollup's, so the CSRs of a workspace still sum to the
     * analytics card:
     *
     *  - The history is the `initial` phone-number report — what the number
     *    looked like when the order came in, which is what the CSR confirming
     *    it could have acted on, and which does not drift afterwards the way
     *    `latest` does.
     *  - Cancelled and removed orders (status 6, 7) are out, as they are
     *    everywhere orders are counted. An order that never shipped never
     *    needed the call.
     *  - Counted on the day of confirmation, so the card is the work the range
     *    created. An order never confirmed has no day to fall in and no CSR
     *    to fall to.
     *
     * The two passes mirror PageOrderReportBreakdownBuilder's own — an inner
     * join for the orders with usable history, an anti-join for the ones
     * without — so the arithmetic matches the rollup rather than merely
     * resembling it.
     *
     * @param  array<int, string>  $pancakeUserIds
     * @return array{orders: int, no_report: int, high_rts: int, risky_orders: int}
     */
    private function ownRiskyOrders(Workspace $workspace, array $pancakeUserIds, string $from, string $to): array
    {
        if ($pancakeUserIds === []) {
            return ['orders' => 0, 'no_report' => 0, 'high_rts' => 0, 'risky_orders' => 0];
        }

        $confirmed = fn () => DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspace->id)
            ->whereIn('pancake_orders.confirmed_by', $pancakeUserIds)
            // A range rather than DATE(confirmed_at) = ..., so the index over
            // (confirmed_by, confirmed_at) stays usable.
            ->whereBetween('pancake_orders.confirmed_at', [
                CarbonImmutable::parse($from)->startOfDay(),
                CarbonImmutable::parse($to)->endOfDay(),
            ])
            ->whereNotIn('pancake_orders.status', [6, 7]);

        // Orders whose customer arrived with real history: how many there were,
        // and how many of those were returning at or above the threshold.
        $withHistory = $confirmed()
            ->join('pancake_order_phone_number_reports as pnr', function ($join) {
                $join->on('pnr.order_id', '=', 'pancake_orders.id')
                    ->where('pnr.type', '=', 'initial')
                    ->whereRaw('pnr.order_fail + pnr.order_success >= 1');
            })
            ->selectRaw('
                COUNT(*) as orders,
                COALESCE(SUM(CASE
                    WHEN pnr.order_fail + pnr.order_success >= ?
                     AND pnr.order_fail / (pnr.order_fail + pnr.order_success) >= ?
                    THEN 1 ELSE 0
                END), 0) as high_rts
            ', [RiskyOrders::MIN_ORDERS, RiskyOrders::RTS_THRESHOLD])
            ->first();

        // And the ones with nothing usable behind them. The anti-join is
        // restricted to reports that actually show prior orders, so this one
        // clause catches both ways an order lands here: no `initial` report at
        // all, and an `initial` report sitting at 0/0.
        $noReport = $confirmed()
            ->leftJoin('pancake_order_phone_number_reports as pnr', function ($join) {
                $join->on('pnr.order_id', '=', 'pancake_orders.id')
                    ->where('pnr.type', '=', 'initial')
                    ->whereRaw('pnr.order_fail + pnr.order_success >= 1');
            })
            ->whereNull('pnr.id')
            ->count();

        $withHistoryCount = (int) ($withHistory->orders ?? 0);
        $highRts = (int) ($withHistory->high_rts ?? 0);

        return [
            'orders' => $withHistoryCount + $noReport,
            'no_report' => $noReport,
            'high_rts' => $highRts,
            'risky_orders' => $noReport + $highRts,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | The arithmetic every card shares
     |--------------------------------------------------------------------------
     */

    /**
     * A count or a magnitude against the period before it, as a percentage.
     *
     * Null rather than zero with nothing to compare against: a 0% would read
     * as "unchanged" rather than as "nothing to measure against".
     */
    private function relativeChange(float|int $current, float|int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /**
     * A rate against the one before it, in percentage points: 12% to 15% is
     * "+3 pts", not "+25%". Null unless both sides exist.
     */
    private function pointChange(?float $current, ?float $previous): ?float
    {
        return $current !== null && $previous !== null ? round($current - $previous, 1) : null;
    }

    /** One figure over another as a percentage; null with nothing to divide by. */
    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /** A per-unit average; null with nothing to divide by. */
    private function average(int $total, int $count): ?float
    {
        return $count > 0 ? round($total / $count, 1) : null;
    }
}

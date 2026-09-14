<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who came top over a range, on each of the four figures the CSR pages rank by.
 *
 * The CSR dashboard's rankings, where a CSR reads the board to know where they
 * stand. The whole roster is ranked, not just the reader: a leader card
 * narrowed to one person would crown them on every figure and say nothing.
 *
 * The CSR analytics page runs the same four rankings from its own controller
 * (CSRController::analyticsLeader*), and the two must stay in step — a CSR and
 * their manager reading different winners off the same week would be a bug
 * neither of them could explain. The queries here are copied from those; keep
 * them that way.
 *
 * `$shopIds` is where the two legitimately differ: analytics passes the shops
 * team visibility allows, the dashboard passes null for every shop in the
 * workspace. Team scoping fails closed for a CSR in no team, and an empty
 * leaderboard protects nothing the workspace's own unauthenticated
 * /api/public/leaderboards endpoints do not already publish.
 *
 * Every method answers the card's `leader` payload, or null when nobody
 * qualified — each figure has its own bar for what counts as being in the
 * running, and they are the reason a quiet week crowns nobody rather than
 * crowning a zero.
 */
class CsrLeaders
{
    /**
     * Highest sales.
     *
     * Off the rollup, so this is the row the CSR table shows: everything
     * confirmed, cancellations included — not the Sales card's rule.
     *
     * @param  array<int, int>|null  $shopIds
     * @return array<string, mixed>|null
     */
    public static function sales(Workspace $workspace, string $from, string $to, ?array $shopIds): ?array
    {
        $perCsr = self::rollup('pancake_user_pos_daily_reports', $workspace, $from, $to, $shopIds)
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
            return null;
        }

        // The CSRs' own total, not the workspace's — an order confirmed by
        // nobody never reaches the rollup, so the shares here add up to 100%.
        $total = (float) $perCsr->sum('sales');
        $sales = (float) $leader->sales;
        $orders = (int) $leader->orders;

        return [
            'name' => $leader->name,
            'value' => $sales,
            'orders' => $orders,
            // What one order was worth on average to this CSR.
            'aov' => $orders > 0 ? round($sales / $orders, 2) : null,
            // Their slice of everything the CSRs confirmed; null when that
            // total is somehow zero.
            'share' => $total > 0 ? round($sales / $total * 100, 1) : null,
        ];
    }

    /**
     * Lowest RTS rate — the one card where low is the win.
     *
     * Who is in the running and what their rate reads are two questions. A CSR
     * earns a place by having at least one shop-day that saw both a return and
     * a delivery — half a parcel's story is no evidence of a rate. The rate
     * itself is then read off everything they settled in the range,
     * delivery-only days included, which is the arithmetic and the row set of
     * the RTS Rate column in the breakdown table.
     *
     * @param  array<int, int>|null  $shopIds
     * @return array<string, mixed>|null
     */
    public static function rts(Workspace $workspace, string $from, string $to, ?array $shopIds): ?array
    {
        $perCsr = self::rollup('pancake_user_pos_daily_reports', $workspace, $from, $to, $shopIds)
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
            return null;
        }

        return [
            'name' => $leader->name,
            'value' => $rate($leader),
            'returned' => (float) $leader->returned_amount,
            'delivered' => (float) $leader->delivered_amount,
            'orders' => (int) $leader->settled_orders,
        ];
    }

    /**
     * Highest RMO % — the CSR table's own: total_called (deliveries assigned to
     * them that moved off PENDING) over total_confirmed (deliveries they
     * confirmed).
     *
     * @param  array<int, int>|null  $shopIds
     * @return array<string, mixed>|null
     */
    public static function rmoCalled(Workspace $workspace, string $from, string $to, ?array $shopIds): ?array
    {
        $leader = self::rollup('pancake_user_daily_call_reports', $workspace, $from, $to, $shopIds)
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
            return null;
        }

        $called = (int) $leader->called;
        $confirmed = (int) $leader->confirmed;

        return [
            'name' => $leader->name,
            'value' => round($called / $confirmed * 100, 1),
            'called' => $called,
            'confirmed' => $confirmed,
        ];
    }

    /**
     * Most time on RMO calls — the CSR table's "RMO Call Time" summed over the
     * range, averaged over the call count sitting beside it in "RMO Called".
     *
     * @param  array<int, int>|null  $shopIds
     * @return array<string, mixed>|null
     */
    public static function rmoDuration(Workspace $workspace, string $from, string $to, ?array $shopIds): ?array
    {
        $leader = self::rollup('pancake_user_daily_call_reports', $workspace, $from, $to, $shopIds)
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
            return null;
        }

        $seconds = (int) $leader->seconds;
        $calls = (int) $leader->calls;

        return [
            'name' => $leader->name,
            'value' => $seconds,
            'calls' => $calls,
            // Null when the rollup recorded time but no calls: the two columns
            // are written independently.
            'average_seconds' => $calls > 0 ? round($seconds / $calls, 1) : null,
        ];
    }

    /**
     * One nightly rollup over a range, joined to the CSR it belongs to and
     * grouped by them — everything the four rankings share before they pick
     * their own columns.
     *
     * `$shopIds` null means every shop in the workspace; an empty array is the
     * fail-closed case a scoped viewer in no team lands on, and narrows to
     * nothing rather than to everything.
     *
     * @param  array<int, int>|null  $shopIds
     */
    private static function rollup(string $table, Workspace $workspace, string $from, string $to, ?array $shopIds): Builder
    {
        return DB::table("{$table} as r")
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $workspace->id)
            ->whereBetween('r.date', [$from, $to])
            ->when($shopIds !== null, fn ($q) => $q->whereIn('r.shop_id', $shopIds))
            ->groupBy('pu.id', 'pu.name');
    }
}

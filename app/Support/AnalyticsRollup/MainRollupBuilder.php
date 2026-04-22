<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Populates workspace_daily_metrics. Pattern:
 *   1. DELETE old rows for (workspace, date).
 *   2. For each column, run a SELECT that aggregates the source table per page_id.
 *      Results are merged into $aggregated: [page_id => [column => value, ...]].
 *   3. One bulk upsert at the end writes all columns for all pages in a single
 *      round trip.
 *
 * One method per column so each aggregate is easy to audit or tweak. Source
 * tables: pancake_orders, parcel_journeys, parcel_journey_notifications.
 */
class MainRollupBuilder
{
    /**
     * Every metric column written by this builder. Used to normalize the upsert
     * payload so every row has the same keys (Laravel's upsert requires it).
     */
    private const COLUMNS = [
        'confirmed_count',
        'shipped_count',
        'first_delivery_attempt_count',
        'delivered_count',
        'returning_count',
        'entered_returning_count',
        'returned_count',
        'for_delivery_count',
        'total_sales',
        'delivered_amount',
        'returning_amount',
        'returned_amount',
        'sum_delivery_attempts_delivered',
        'sum_delivery_attempts_returned',
        'sum_customer_rts_rate_delivered',
        'sum_customer_rts_rate_returned',
        'sum_days_confirmed_to_shipped',
        'count_confirmed_to_shipped',
        'sum_days_confirmed_to_first_attempt',
        'count_confirmed_to_first_attempt',
        'sum_days_confirmed_to_delivered',
        'count_confirmed_to_delivered',
        'sum_days_shipped_to_first_attempt',
        'count_shipped_to_first_attempt',
        'sum_days_shipped_to_delivered',
        'count_shipped_to_delivered',
        'sum_days_returning_to_returned',
        'count_returning_to_returned',
        'tracked_orders_count',
        'sms_sent_count',
        'chat_sent_count',
    ];

    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        // In-memory aggregation: page_id => [column => value, ...]
        $aggregated = [];

        // --- confirmed_at bucket ---
        $this->populateConfirmedCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateTotalSales($aggregated, $workspaceId, $start, $endExclusive);

        // --- shipped_at bucket ---
        $this->populateShippedCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysConfirmedToShipped($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountConfirmedToShipped($aggregated, $workspaceId, $start, $endExclusive);

        // --- first_delivery_attempt bucket ---
        $this->populateFirstDeliveryAttemptCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysConfirmedToFirstAttempt($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountConfirmedToFirstAttempt($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysShippedToFirstAttempt($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountShippedToFirstAttempt($aggregated, $workspaceId, $start, $endExclusive);

        // --- delivered_at bucket ---
        $this->populateDeliveredCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateDeliveredAmount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDeliveryAttemptsDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumCustomerRtsRateDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysConfirmedToDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountConfirmedToDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysShippedToDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountShippedToDelivered($aggregated, $workspaceId, $start, $endExclusive);

        // --- returning_at bucket (still in transit) ---
        $this->populateReturningCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateReturningAmount($aggregated, $workspaceId, $start, $endExclusive);

        // --- returning_at bucket (any state) ---
        $this->populateEnteredReturningCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDeliveryAttemptsReturned($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumCustomerRtsRateReturned($aggregated, $workspaceId, $start, $endExclusive);

        // --- returned_at bucket ---
        $this->populateReturnedCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateReturnedAmount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSumDaysReturningToReturned($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateCountReturningToReturned($aggregated, $workspaceId, $start, $endExclusive);

        // --- parcel_journeys ---
        $this->populateForDeliveryCount($aggregated, $workspaceId, $start, $endExclusive);

        // --- parcel_journey_notifications ---
        $this->populateTrackedOrdersCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateSmsSentCount($aggregated, $workspaceId, $start, $endExclusive);
        $this->populateChatSentCount($aggregated, $workspaceId, $start, $endExclusive);

        // Aggregation complete — wipe old rows and flush the new ones.
        DB::table('workspace_daily_metrics')
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->delete();

        $this->upsertAggregated($workspaceId, $date, $aggregated);
    }

    public function forDateRange(int $workspaceId, string $from, string $to): void
    {
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lte($end)) {
            $this->forDate($workspaceId, $cursor->toDateString());
            $cursor = $cursor->addDay();
        }
    }

    // ================================================================
    // confirmed_at bucket
    // ================================================================

    private function populateConfirmedCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'confirmed_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'confirmed_at',
            extraWhere: 'AND po.status NOT IN (6, 7)'));
    }

    private function populateTotalSales(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'total_sales', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.final_amount), 0)',
            dateColumn: 'confirmed_at',
            extraWhere: 'AND po.status NOT IN (6, 7)'));
    }

    // ================================================================
    // shipped_at bucket
    // ================================================================

    private function populateShippedCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'shipped_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'shipped_at'));
    }

    private function populateSumDaysConfirmedToShipped(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_confirmed_to_shipped', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.shipped_at) ELSE 0 END), 0)',
            dateColumn: 'shipped_at'));
    }

    private function populateCountConfirmedToShipped(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_confirmed_to_shipped', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'shipped_at'));
    }

    // ================================================================
    // first_delivery_attempt bucket
    // ================================================================

    private function populateFirstDeliveryAttemptCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'first_delivery_attempt_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'first_delivery_attempt'));
    }

    private function populateSumDaysConfirmedToFirstAttempt(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_confirmed_to_first_attempt', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.first_delivery_attempt) ELSE 0 END), 0)',
            dateColumn: 'first_delivery_attempt'));
    }

    private function populateCountConfirmedToFirstAttempt(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_confirmed_to_first_attempt', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'first_delivery_attempt'));
    }

    private function populateSumDaysShippedToFirstAttempt(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_shipped_to_first_attempt', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.shipped_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.shipped_at, po.first_delivery_attempt) ELSE 0 END), 0)',
            dateColumn: 'first_delivery_attempt'));
    }

    private function populateCountShippedToFirstAttempt(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_shipped_to_first_attempt', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.shipped_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'first_delivery_attempt'));
    }

    // ================================================================
    // delivered_at bucket
    // ================================================================

    private function populateDeliveredCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'delivered_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'delivered_at'));
    }

    private function populateDeliveredAmount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'delivered_amount', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.final_amount), 0)',
            dateColumn: 'delivered_at'));
    }

    private function populateSumDeliveryAttemptsDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_delivery_attempts_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.delivery_attempts), 0)',
            dateColumn: 'delivered_at'));
    }

    private function populateSumCustomerRtsRateDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_customer_rts_rate_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(COALESCE(pr.customer_rts_rate, 0)), 0)',
            dateColumn: 'delivered_at',
            extraJoin: $this->phoneReportsJoin()));
    }

    private function populateSumDaysConfirmedToDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_confirmed_to_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.delivered_at) ELSE 0 END), 0)',
            dateColumn: 'delivered_at'));
    }

    private function populateCountConfirmedToDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_confirmed_to_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.confirmed_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'delivered_at'));
    }

    private function populateSumDaysShippedToDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_shipped_to_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.shipped_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.shipped_at, po.delivered_at) ELSE 0 END), 0)',
            dateColumn: 'delivered_at'));
    }

    private function populateCountShippedToDelivered(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_shipped_to_delivered', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.shipped_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'delivered_at'));
    }

    // ================================================================
    // returning_at bucket — still in transit (returned_at IS NULL)
    // ================================================================

    private function populateReturningCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'returning_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'returning_at',
            extraWhere: 'AND po.returned_at IS NULL'));
    }

    private function populateReturningAmount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'returning_amount', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.final_amount), 0)',
            dateColumn: 'returning_at',
            extraWhere: 'AND po.returned_at IS NULL'));
    }

    // ================================================================
    // returning_at bucket — any state (entered return flow)
    // ================================================================

    private function populateEnteredReturningCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'entered_returning_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'returning_at'));
    }

    private function populateSumDeliveryAttemptsReturned(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_delivery_attempts_returned', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.delivery_attempts), 0)',
            dateColumn: 'returning_at'));
    }

    private function populateSumCustomerRtsRateReturned(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_customer_rts_rate_returned', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(COALESCE(pr.customer_rts_rate, 0)), 0)',
            dateColumn: 'returning_at',
            extraJoin: $this->phoneReportsJoin()));
    }

    // ================================================================
    // returned_at bucket
    // ================================================================

    private function populateReturnedCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'returned_count', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            dateColumn: 'returned_at'));
    }

    private function populateReturnedAmount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'returned_amount', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(po.final_amount), 0)',
            dateColumn: 'returned_at'));
    }

    private function populateSumDaysReturningToReturned(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sum_days_returning_to_returned', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'COALESCE(SUM(CASE WHEN po.status NOT IN (6,7) AND po.returning_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, po.returning_at, po.returned_at) ELSE 0 END), 0)',
            dateColumn: 'returned_at'));
    }

    private function populateCountReturningToReturned(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'count_returning_to_returned', $this->selectFromPancakeOrders($ws, $s, $e,
            valueExpr: 'SUM(CASE WHEN po.status NOT IN (6,7) AND po.returning_at IS NOT NULL THEN 1 ELSE 0 END)',
            dateColumn: 'returned_at'));
    }

    // ================================================================
    // parcel_journeys
    // ================================================================

    private function populateForDeliveryCount(array &$agg, int $ws, string $s, string $e): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT po.page_id, COUNT(DISTINCT po.id) AS value
            FROM parcel_journeys pj
            INNER JOIN pancake_orders po ON po.id = pj.order_id
            WHERE po.workspace_id = ?
              AND pj.status = 'On Delivery'
              AND pj.created_at >= ?
              AND pj.created_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.page_id
            SQL, [$ws, $s, $e]);

        $this->collect($agg, 'for_delivery_count', $rows);
    }

    // ================================================================
    // parcel_journey_notifications
    // ================================================================

    private function populateTrackedOrdersCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'tracked_orders_count', $this->selectFromNotifications($ws, $s, $e,
            valueExpr: 'COUNT(DISTINCT po.id)',
            extraWhere: "AND pjn.status = 'sent'"));
    }

    private function populateSmsSentCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'sms_sent_count', $this->selectFromNotifications($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            extraWhere: "AND pjn.status = 'sent' AND pjn.type = 'sms'"));
    }

    private function populateChatSentCount(array &$agg, int $ws, string $s, string $e): void
    {
        $this->collect($agg, 'chat_sent_count', $this->selectFromNotifications($ws, $s, $e,
            valueExpr: 'COUNT(*)',
            extraWhere: "AND pjn.status = 'sent' AND pjn.type = 'chat'"));
    }

    // ================================================================
    // shared helpers
    // ================================================================

    /**
     * SELECT per-page aggregated values from pancake_orders.
     */
    private function selectFromPancakeOrders(
        int $workspaceId,
        string $start,
        string $endExclusive,
        string $valueExpr,
        string $dateColumn,
        string $extraWhere = '',
        string $extraJoin = '',
    ): array {
        $sql = <<<SQL
            SELECT po.page_id, {$valueExpr} AS value
            FROM pancake_orders po
            {$extraJoin}
            WHERE po.workspace_id = ?
              AND po.page_id IS NOT NULL
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
              {$extraWhere}
            GROUP BY po.page_id
            SQL;

        return DB::select($sql, [$workspaceId, $start, $endExclusive]);
    }

    /**
     * SELECT per-page aggregated values from parcel_journey_notifications
     * joined to pancake_orders, bucketed by pjn.created_at.
     */
    private function selectFromNotifications(
        int $workspaceId,
        string $start,
        string $endExclusive,
        string $valueExpr,
        string $extraWhere = '',
    ): array {
        $sql = <<<SQL
            SELECT po.page_id, {$valueExpr} AS value
            FROM parcel_journey_notifications pjn
            INNER JOIN pancake_orders po ON po.id = pjn.order_id
            WHERE po.workspace_id = ?
              AND po.page_id IS NOT NULL
              AND pjn.created_at >= ?
              AND pjn.created_at < ?
              {$extraWhere}
            GROUP BY po.page_id
            SQL;

        return DB::select($sql, [$workspaceId, $start, $endExclusive]);
    }

    /**
     * Merge SELECT results into the aggregated map keyed by page_id.
     */
    private function collect(array &$aggregated, string $column, array $rows): void
    {
        foreach ($rows as $row) {
            $aggregated[$row->page_id][$column] = $row->value;
        }
    }

    /**
     * One bulk upsert for all columns × all pages. Each payload row is
     * normalized so every key in self::COLUMNS is present (Laravel's upsert
     * requires consistent row shape).
     */
    private function upsertAggregated(int $workspaceId, string $date, array $aggregated): void
    {
        if (empty($aggregated)) {
            return;
        }

        $now = Carbon::now();
        $defaults = array_fill_keys(self::COLUMNS, 0);

        $payload = [];
        foreach ($aggregated as $pageId => $columns) {
            $payload[] = array_merge(
                [
                    'workspace_id' => $workspaceId,
                    'date' => $date,
                    'page_id' => $pageId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $defaults,
                $columns,
            );
        }

        DB::table('workspace_daily_metrics')->upsert(
            $payload,
            ['workspace_id', 'date', 'page_id'],
            [...self::COLUMNS, 'updated_at'],
        );
    }

    /**
     * LEFT JOIN subquery for per-order customer_rts_rate from phone reports.
     */
    private function phoneReportsJoin(): string
    {
        return <<<'SQL'
            LEFT JOIN (
                SELECT
                    order_id,
                    COALESCE(SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0), 0) AS customer_rts_rate
                FROM pancake_order_phone_number_reports
                GROUP BY order_id
            ) pr ON pr.order_id = po.id
            SQL;
    }
}

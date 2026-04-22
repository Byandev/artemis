<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MainRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_daily_metrics')
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->delete();

            $this->aggregateConfirmed($workspaceId, $date, $start, $endExclusive);
            $this->aggregateShipped($workspaceId, $date, $start, $endExclusive);
            $this->aggregateFirstAttempt($workspaceId, $date, $start, $endExclusive);
            $this->aggregateDelivered($workspaceId, $date, $start, $endExclusive);
            $this->aggregateDeliveredClean($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturning($workspaceId, $date, $start, $endExclusive);
            $this->aggregateEnteredReturning($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturned($workspaceId, $date, $start, $endExclusive);
            $this->aggregateForDelivery($workspaceId, $date, $start, $endExclusive);
            $this->aggregateNotifications($workspaceId, $date, $start, $endExclusive);
        });
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

    /**
     * TotalSales / TotalOrders / Aov: confirmed_at in range, status NOT IN (6,7).
     */
    private function aggregateConfirmed(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                confirmed_count, total_sales,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS total_sales,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.status NOT IN (6, 7)
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                confirmed_count = VALUES(confirmed_count),
                total_sales = VALUES(total_sales),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * AverageDaysFromConfirmedToShipped: shipped_at in range, status NOT IN (6,7), confirmed_at NOT NULL.
     */
    private function aggregateShipped(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                shipped_count,
                sum_days_confirmed_to_shipped, count_confirmed_to_shipped,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS shipped_count,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.shipped_at)
                    ELSE 0 END), 0) AS sum_days_confirmed_to_shipped,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_confirmed_to_shipped,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.shipped_at >= ?
              AND po.shipped_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                shipped_count = VALUES(shipped_count),
                sum_days_confirmed_to_shipped = VALUES(sum_days_confirmed_to_shipped),
                count_confirmed_to_shipped = VALUES(count_confirmed_to_shipped),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * AverageDaysFromConfirmedToFirstAttempt / AverageDaysFromShippedToFirstAttempt:
     * first_delivery_attempt in range, status NOT IN (6,7), start column NOT NULL.
     */
    private function aggregateFirstAttempt(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                first_delivery_attempt_count,
                sum_days_confirmed_to_first_attempt, count_confirmed_to_first_attempt,
                sum_days_shipped_to_first_attempt, count_shipped_to_first_attempt,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS first_delivery_attempt_count,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.first_delivery_attempt)
                    ELSE 0 END), 0) AS sum_days_confirmed_to_first_attempt,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_confirmed_to_first_attempt,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.shipped_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.shipped_at, po.first_delivery_attempt)
                    ELSE 0 END), 0) AS sum_days_shipped_to_first_attempt,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.shipped_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_shipped_to_first_attempt,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.first_delivery_attempt >= ?
              AND po.first_delivery_attempt < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                first_delivery_attempt_count = VALUES(first_delivery_attempt_count),
                sum_days_confirmed_to_first_attempt = VALUES(sum_days_confirmed_to_first_attempt),
                count_confirmed_to_first_attempt = VALUES(count_confirmed_to_first_attempt),
                sum_days_shipped_to_first_attempt = VALUES(sum_days_shipped_to_first_attempt),
                count_shipped_to_first_attempt = VALUES(count_shipped_to_first_attempt),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * DeliveredAmount: delivered_at in range, no status filter.
     * Also populates sum_days_*_to_delivered pairs (with status NOT IN (6,7) for avg days metrics).
     */
    private function aggregateDelivered(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                delivered_count, delivered_amount,
                sum_days_confirmed_to_delivered, count_confirmed_to_delivered,
                sum_days_shipped_to_delivered, count_shipped_to_delivered,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS delivered_count,
                COALESCE(SUM(po.final_amount), 0) AS delivered_amount,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.confirmed_at, po.delivered_at)
                    ELSE 0 END), 0) AS sum_days_confirmed_to_delivered,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.confirmed_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_confirmed_to_delivered,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.shipped_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.shipped_at, po.delivered_at)
                    ELSE 0 END), 0) AS sum_days_shipped_to_delivered,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.shipped_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_shipped_to_delivered,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.delivered_at >= ?
              AND po.delivered_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                delivered_count = VALUES(delivered_count),
                delivered_amount = VALUES(delivered_amount),
                sum_days_confirmed_to_delivered = VALUES(sum_days_confirmed_to_delivered),
                count_confirmed_to_delivered = VALUES(count_confirmed_to_delivered),
                sum_days_shipped_to_delivered = VALUES(sum_days_shipped_to_delivered),
                count_shipped_to_delivered = VALUES(count_shipped_to_delivered),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * DeliveredAvgDeliveryAttempts + DeliveredAvgCustomerRts:
     * delivered_at in range, returning_at IS NULL, no status filter.
     * Denominator = delivered_clean_count.
     */
    private function aggregateDeliveredClean(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                delivered_clean_count,
                sum_delivery_attempts_delivered,
                sum_customer_rts_rate_delivered,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS delivered_clean_count,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_delivered,
                COALESCE(SUM(COALESCE(pr.customer_rts_rate, 0)), 0) AS sum_customer_rts_rate_delivered,
                NOW(), NOW()
            FROM pancake_orders po
            LEFT JOIN (
                SELECT
                    order_id,
                    COALESCE(SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0), 0) AS customer_rts_rate
                FROM pancake_order_phone_number_reports
                GROUP BY order_id
            ) pr ON pr.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.delivered_at >= ?
              AND po.delivered_at < ?
              AND po.returning_at IS NULL
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                delivered_clean_count = VALUES(delivered_clean_count),
                sum_delivery_attempts_delivered = VALUES(sum_delivery_attempts_delivered),
                sum_customer_rts_rate_delivered = VALUES(sum_customer_rts_rate_delivered),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * ReturningAmount: returning_at in range, returned_at IS NULL, no status filter.
     */
    private function aggregateReturning(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                returning_count, returning_amount,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS returning_count,
                COALESCE(SUM(po.final_amount), 0) AS returning_amount,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.returned_at IS NULL
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                returning_count = VALUES(returning_count),
                returning_amount = VALUES(returning_amount),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * ReturnedAvgDeliveryAttempts + ReturnedAvgCustomerRts:
     * returning_at in range, no status filter, no returned_at filter.
     * Denominator = entered_returning_count.
     */
    private function aggregateEnteredReturning(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                entered_returning_count,
                sum_delivery_attempts_returned,
                sum_customer_rts_rate_returned,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS entered_returning_count,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_returned,
                COALESCE(SUM(COALESCE(pr.customer_rts_rate, 0)), 0) AS sum_customer_rts_rate_returned,
                NOW(), NOW()
            FROM pancake_orders po
            LEFT JOIN (
                SELECT
                    order_id,
                    COALESCE(SUM(order_fail) / NULLIF(SUM(order_fail) + SUM(order_success), 0), 0) AS customer_rts_rate
                FROM pancake_order_phone_number_reports
                GROUP BY order_id
            ) pr ON pr.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                entered_returning_count = VALUES(entered_returning_count),
                sum_delivery_attempts_returned = VALUES(sum_delivery_attempts_returned),
                sum_customer_rts_rate_returned = VALUES(sum_customer_rts_rate_returned),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * ReturnedAmount: returned_at in range, no status filter.
     * Also populates sum_days_returning_to_returned (status NOT IN (6,7) for avg days).
     */
    private function aggregateReturned(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                returned_count, returned_amount,
                sum_days_returning_to_returned, count_returning_to_returned,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS returned_count,
                COALESCE(SUM(po.final_amount), 0) AS returned_amount,
                COALESCE(SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.returning_at IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, po.returning_at, po.returned_at)
                    ELSE 0 END), 0) AS sum_days_returning_to_returned,
                SUM(CASE
                    WHEN po.status NOT IN (6, 7) AND po.returning_at IS NOT NULL
                    THEN 1 ELSE 0 END) AS count_returning_to_returned,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.returned_at >= ?
              AND po.returned_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                returned_count = VALUES(returned_count),
                returned_amount = VALUES(returned_amount),
                sum_days_returning_to_returned = VALUES(sum_days_returning_to_returned),
                count_returning_to_returned = VALUES(count_returning_to_returned),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * TotalForDeliveryCount: distinct orders with a parcel_journey row of status='On Delivery'
     * bucketed by pj.created_at. An order with multiple 'On Delivery' rows counts once.
     */
    private function aggregateForDelivery(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                for_delivery_count,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(DISTINCT po.id) AS for_delivery_count,
                NOW(), NOW()
            FROM parcel_journeys pj
            INNER JOIN pancake_orders po ON po.id = pj.order_id
            WHERE po.workspace_id = ?
              AND pj.status = 'On Delivery'
              AND pj.created_at >= ?
              AND pj.created_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                for_delivery_count = VALUES(for_delivery_count),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * TrackedOrdersCount / SmsSentCount / ChatSentCount:
     * parcel_journey_notifications with status='sent', bucketed by pjn.created_at.
     */
    private function aggregateNotifications(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                tracked_orders_count, sms_sent_count, chat_sent_count,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(DISTINCT po.id) AS tracked_orders_count,
                SUM(CASE WHEN pjn.type = 'sms' THEN 1 ELSE 0 END) AS sms_sent_count,
                SUM(CASE WHEN pjn.type = 'chat' THEN 1 ELSE 0 END) AS chat_sent_count,
                NOW(), NOW()
            FROM parcel_journey_notifications pjn
            INNER JOIN pancake_orders po ON po.id = pjn.order_id
            WHERE po.workspace_id = ?
              AND pjn.status = 'sent'
              AND pjn.created_at >= ?
              AND pjn.created_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                tracked_orders_count = VALUES(tracked_orders_count),
                sms_sent_count = VALUES(sms_sent_count),
                chat_sent_count = VALUES(chat_sent_count),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }
}

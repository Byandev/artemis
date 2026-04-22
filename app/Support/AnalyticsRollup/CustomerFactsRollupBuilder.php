<?php

namespace App\Support\AnalyticsRollup;

use Illuminate\Support\Facades\DB;

/**
 * Rebuilds workspace_customer_facts rows. Grain is per-customer (not per-day),
 * so the forDate/forDateRange interface still matches other builders but the
 * date is treated as a "dirty window": only customers whose orders' updated_at
 * falls in that window get their facts recomputed. Backfill with a wide window
 * to rebuild everything.
 */
class CustomerFactsRollupBuilder
{
    /**
     * Per-process memo so the builder only rebuilds each workspace once per
     * command invocation, even if the orchestrator's outer loop calls it
     * multiple times (e.g., backfill chunk walk). The first call wins — for
     * backfill, pass the full history window in one chunk (--chunk-days=9999)
     * so that "first call" uses the widest dirty window.
     */
    private array $processed = [];

    public function forDate(int $workspaceId, string $date): void
    {
        $this->forWorkspaceOnce($workspaceId, $date.' 00:00:00');
    }

    public function forDateRange(int $workspaceId, string $from, string $to): void
    {
        $this->forWorkspaceOnce($workspaceId, $from.' 00:00:00');
    }

    private function forWorkspaceOnce(int $workspaceId, string $dirtySince): void
    {
        if (isset($this->processed[$workspaceId])) {
            return;
        }
        $this->processed[$workspaceId] = true;
        $this->rebuildDirty($workspaceId, $dirtySince);
    }

    private function rebuildDirty(int $workspaceId, string $dirtySince): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_customer_facts (
                workspace_id, customer_id,
                first_confirmed_at, last_confirmed_at, first_confirmed_page_id,
                total_confirmed_orders, total_delivered_orders,
                total_confirmed_spend, total_delivered_spend,
                customer_created_at,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                po.customer_id,
                MIN(po.confirmed_at) AS first_confirmed_at,
                MAX(po.confirmed_at) AS last_confirmed_at,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(po.page_id ORDER BY po.confirmed_at ASC),
                    ',', 1
                ) AS first_confirmed_page_id,
                COUNT(*) AS total_confirmed_orders,
                SUM(CASE WHEN po.status = 3 THEN 1 ELSE 0 END) AS total_delivered_orders,
                COALESCE(SUM(po.final_amount), 0) AS total_confirmed_spend,
                COALESCE(SUM(CASE WHEN po.status = 3 THEN po.final_amount ELSE 0 END), 0) AS total_delivered_spend,
                MAX(pc.created_at) AS customer_created_at,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN (
                SELECT DISTINCT customer_id
                FROM pancake_orders
                WHERE workspace_id = ?
                  AND customer_id IS NOT NULL
                  AND updated_at >= ?
            ) dirty ON dirty.customer_id = po.customer_id
            LEFT JOIN pancake_customers pc ON pc.customer_id = po.customer_id
            WHERE po.workspace_id = ?
              AND po.customer_id IS NOT NULL
              AND po.confirmed_at IS NOT NULL
              AND po.status NOT IN (6, 7)
            GROUP BY po.workspace_id, po.customer_id
            ON DUPLICATE KEY UPDATE
                first_confirmed_at = VALUES(first_confirmed_at),
                last_confirmed_at = VALUES(last_confirmed_at),
                first_confirmed_page_id = VALUES(first_confirmed_page_id),
                total_confirmed_orders = VALUES(total_confirmed_orders),
                total_delivered_orders = VALUES(total_delivered_orders),
                total_confirmed_spend = VALUES(total_confirmed_spend),
                total_delivered_spend = VALUES(total_delivered_spend),
                customer_created_at = VALUES(customer_created_at),
                updated_at = NOW()
            SQL, [$workspaceId, $dirtySince, $workspaceId]);
    }
}

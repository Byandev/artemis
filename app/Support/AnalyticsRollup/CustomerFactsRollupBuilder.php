<?php

namespace App\Support\AnalyticsRollup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds workspace_customer_facts rows. Grain is per-customer (not per-day),
 * so the forDate/forDateRange interface still matches other builders but the
 * date is treated as a "dirty window": only customers whose orders' updated_at
 * falls in that window get their facts recomputed. Backfill with a wide window
 * to rebuild everything.
 *
 * Pattern: find dirty customer_ids, then process in chunks — for each chunk,
 * SELECT the per-customer lifetime aggregates into PHP, then bulk upsert.
 */
class CustomerFactsRollupBuilder
{
    /**
     * Max customer_ids handled per DB round trip. Keeps IN clauses reasonable
     * and PHP memory bounded for large dirty sets.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Columns written by this builder (excluding the unique key and timestamps).
     */
    private const COLUMNS = [
        'first_confirmed_at',
        'last_confirmed_at',
        'first_confirmed_page_id',
        'total_confirmed_orders',
        'total_delivered_orders',
        'total_confirmed_spend',
        'total_delivered_spend',
        'customer_created_at',
    ];

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
        $dirtyIds = $this->collectDirtyCustomerIds($workspaceId, $dirtySince);

        if (empty($dirtyIds)) {
            return;
        }

        foreach (array_chunk($dirtyIds, self::CHUNK_SIZE) as $chunk) {
            $rows = $this->collectFactsFor($workspaceId, $chunk);
            $this->upsertFacts($workspaceId, $rows);
        }
    }

    /**
     * Customer IDs whose orders have been touched since $dirtySince.
     * Returned as a flat string array.
     */
    private function collectDirtyCustomerIds(int $workspaceId, string $dirtySince): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT DISTINCT customer_id
            FROM pancake_orders
            WHERE workspace_id = ?
              AND customer_id IS NOT NULL
              AND updated_at >= ?
            SQL, [$workspaceId, $dirtySince]);

        return array_column($rows, 'customer_id');
    }

    /**
     * For a chunk of customer_ids, compute lifetime aggregates from all their
     * confirmed orders (status NOT IN (6,7)).
     */
    private function collectFactsFor(int $workspaceId, array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

        $sql = <<<SQL
            SELECT
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
                MAX(pc.created_at) AS customer_created_at
            FROM pancake_orders po
            LEFT JOIN pancake_customers pc ON pc.customer_id = po.customer_id
            WHERE po.workspace_id = ?
              AND po.customer_id IN ({$placeholders})
              AND po.customer_id IS NOT NULL
              AND po.confirmed_at IS NOT NULL
              AND po.status NOT IN (6, 7)
            GROUP BY po.customer_id
            SQL;

        return DB::select($sql, [$workspaceId, ...$customerIds]);
    }

    private function upsertFacts(int $workspaceId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $now = Carbon::now();
        $payload = array_map(fn ($row) => [
            'workspace_id' => $workspaceId,
            'customer_id' => $row->customer_id,
            'first_confirmed_at' => $row->first_confirmed_at,
            'last_confirmed_at' => $row->last_confirmed_at,
            'first_confirmed_page_id' => $row->first_confirmed_page_id,
            'total_confirmed_orders' => $row->total_confirmed_orders,
            'total_delivered_orders' => $row->total_delivered_orders,
            'total_confirmed_spend' => $row->total_confirmed_spend,
            'total_delivered_spend' => $row->total_delivered_spend,
            'customer_created_at' => $row->customer_created_at,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        DB::table('workspace_customer_facts')->upsert(
            $payload,
            ['workspace_id', 'customer_id'],
            [...self::COLUMNS, 'updated_at'],
        );
    }
}

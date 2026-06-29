<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised "last successful ERP sync" timestamps per inventory item so the
 * sync-monitoring board can sort/filter every item by recency in SQL without
 * scanning the chunk history. Kept fresh by ErpSyncChunk when a chunk succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->timestamp('last_transaction_synced_at')->nullable()->after('remaining_qty');
            $table->timestamp('last_purchase_order_synced_at')->nullable()->after('last_transaction_synced_at');

            $table->index('last_transaction_synced_at');
            $table->index('last_purchase_order_synced_at');
        });

        $this->backfillFromChunks();
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['last_transaction_synced_at']);
            $table->dropIndex(['last_purchase_order_synced_at']);
            $table->dropColumn(['last_transaction_synced_at', 'last_purchase_order_synced_at']);
        });
    }

    /**
     * Seed the new columns from any sync chunks that already succeeded, taking
     * the most recent timestamp per item per type. A no-op on a fresh install
     * where no chunks exist yet.
     */
    private function backfillFromChunks(): void
    {
        if (! Schema::hasTable('gencys_erp_sync_chunks')) {
            return;
        }

        $latest = ['transaction_history' => [], 'purchase_order' => []];

        DB::table('gencys_erp_sync_chunks')
            ->whereIn('status', ['sent', 'confirmed'])
            ->orderBy('id')
            ->select(['type', 'item_ids', 'dispatched_at', 'confirmed_at'])
            ->each(function ($chunk) use (&$latest) {
                $timestamp = $chunk->confirmed_at ?? $chunk->dispatched_at;

                if (! $timestamp || ! isset($latest[$chunk->type])) {
                    return;
                }

                foreach (json_decode($chunk->item_ids, true) ?: [] as $itemId) {
                    $current = $latest[$chunk->type][$itemId] ?? null;

                    if ($current === null || $timestamp > $current) {
                        $latest[$chunk->type][$itemId] = $timestamp;
                    }
                }
            });

        $columns = [
            'transaction_history' => 'last_transaction_synced_at',
            'purchase_order' => 'last_purchase_order_synced_at',
        ];

        foreach ($columns as $type => $column) {
            foreach ($latest[$type] as $itemId => $timestamp) {
                DB::table('inventory_items')->where('id', $itemId)->update([$column => $timestamp]);
            }
        }
    }
};

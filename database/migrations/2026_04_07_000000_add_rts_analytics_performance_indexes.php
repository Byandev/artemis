<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- pancake_orders ---
        // Composite indexes for the RtsBaseQuery date + status filter.
        // Replaces the non-sargable DATE(column) pattern with direct range scans.
        Schema::table('pancake_orders', function (Blueprint $table) {
            $existing = $this->existingIndexes('pancake_orders');

            if (! in_array('idx_orders_workspace_status_delivered', $existing)) {
                $table->index(['workspace_id', 'status', 'delivered_at'], 'idx_orders_workspace_status_delivered');
            }

            if (! in_array('idx_orders_workspace_status_returning', $existing)) {
                $table->index(['workspace_id', 'status', 'returning_at'], 'idx_orders_workspace_status_returning');
            }

            if (! in_array('idx_orders_ad_id', $existing)) {
                $table->index('ad_id', 'idx_orders_ad_id');
            }

            if (! in_array('idx_orders_confirmed_by', $existing)) {
                $table->index('confirmed_by', 'idx_orders_confirmed_by');
            }
        });

        // --- pancake_order_items ---
        // No index on order_id — the RtsOrderItemQuery JOIN was doing a full table scan.
        Schema::table('pancake_order_items', function (Blueprint $table) {
            $existing = $this->existingIndexes('pancake_order_items');

            if (! in_array('idx_order_items_order_id', $existing)) {
                $table->index('order_id', 'idx_order_items_order_id');
            }
        });

        // --- pancake_order_phone_number_reports ---
        // Existing index is on (order_id, phone_number).
        // RtsCxQuery JOINs on (order_id, type) — the type column was unindexed.
        Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
            $existing = $this->existingIndexes('pancake_order_phone_number_reports');

            if (! in_array('idx_pnr_order_type', $existing)) {
                $table->index(['order_id', 'type'], 'idx_pnr_order_type');
            }
        });

        // --- parcel_journeys ---
        // RtsRiderQuery subquery: SELECT MAX(id) WHERE status = 'On Delivery' GROUP BY order_id
        // Existing (status, created_at) index doesn't cover order_id.
        // (status, order_id) lets MySQL resolve the subquery with an index-only scan.
        Schema::table('parcel_journeys', function (Blueprint $table) {
            $existing = $this->existingIndexes('parcel_journeys');

            if (! in_array('idx_parcel_journeys_status_order', $existing)) {
                $table->index(['status', 'order_id'], 'idx_parcel_journeys_status_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_orders_workspace_status_delivered');
            $table->dropIndexIfExists('idx_orders_workspace_status_returning');
            $table->dropIndexIfExists('idx_orders_ad_id');
            $table->dropIndexIfExists('idx_orders_confirmed_by');
        });

        Schema::table('pancake_order_items', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_order_items_order_id');
        });

        Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_pnr_order_type');
        });

        Schema::table('parcel_journeys', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_parcel_journeys_status_order');
        });
    }

    private function existingIndexes(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique()
            ->all();
    }
};

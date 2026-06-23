<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity of a delivery row: the order, the day, and which rider carried it.
     * Lets us upsert (INSERT ... ON DUPLICATE KEY UPDATE) when transferring data
     * between servers. This is a subset of SyncParcelTrackingAction's firstOrCreate
     * key (page/shop/workspace are already implied by order_id), so the sync's
     * normal inserts won't violate it.
     *
     * NOTE: if the table already contains rows that duplicate on these four
     * columns, dedupe them before running this migration or it will fail.
     */
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unique(
                ['order_id', 'delivery_date', 'rider_name', 'rider_phone'],
                'pofd_order_date_rider_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropUnique('pofd_order_date_rider_unique');
        });
    }
};

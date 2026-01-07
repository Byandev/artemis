<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            // Foreign key index for whereHas queries
            $table->index('order_id', 'idx_parcel_journey_notifications_order_id');

            // Indexes for filtering and sorting
            $table->index('created_at', 'idx_parcel_journey_notifications_created_at');
            $table->index('type', 'idx_parcel_journey_notifications_type');
            $table->index('status', 'idx_parcel_journey_notifications_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_parcel_journey_notifications_order_id');
            $table->dropIndex('idx_parcel_journey_notifications_created_at');
            $table->dropIndex('idx_parcel_journey_notifications_type');
            $table->dropIndex('idx_parcel_journey_notifications_status');
        });
    }
};

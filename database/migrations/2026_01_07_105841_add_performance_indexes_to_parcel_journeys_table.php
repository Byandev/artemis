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
        Schema::table('parcel_journeys', function (Blueprint $table) {
            // Foreign key index for JOIN operations (rider name sorting)
            $table->index('order_id', 'idx_parcel_journeys_order_id');

            // Composite index for "on delivery today" queries
            $table->index(['status', 'created_at'], 'idx_parcel_journeys_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_journeys', function (Blueprint $table) {
            $table->dropIndex('idx_parcel_journeys_order_id');
            $table->dropIndex('idx_parcel_journeys_status_created');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fingerprint of the Pancake payload an order was last synced from.
     *
     * The hourly fetch re-reads a day of orders, and the shipped pulls re-read
     * every shipped order, so most of what SyncOrder is handed has not changed
     * since the last pass. Matching the fingerprint lets it skip the full
     * rewrite of the order and its customer, items, address, journeys and
     * phone reports.
     */
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->char('sync_hash', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropColumn('sync_hash');
        });
    }
};

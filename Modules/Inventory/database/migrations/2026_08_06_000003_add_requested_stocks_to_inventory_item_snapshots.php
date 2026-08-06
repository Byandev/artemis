<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Units owed on orders that have been raised but not yet released to a
     * supplier. Split out of waiting_for_delivery_stocks, which now covers only
     * orders a supplier actually has — see PurchasedOrder::RELEASED_STATUSES.
     *
     * Nullable with no backfill: snapshots taken before this split cannot be
     * reconstructed, and a 0 there would read as "nothing was pending" rather
     * than "we did not record it".
     */
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->integer('requested_stocks')->nullable()->after('waiting_for_delivery_stocks');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn('requested_stocks');
        });
    }
};

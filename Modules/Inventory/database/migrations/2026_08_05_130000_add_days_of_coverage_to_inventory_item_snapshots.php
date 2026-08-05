<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catch the snapshot up with inventory_items.days_of_coverage, added after the
 * snapshot table itself. Two columns: the stored buffer as it read that day, and
 * po_qty — the buffer × the 3-day average — which the list now shows as its own
 * column and folds into PO Needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            // Same default as inventory_items, so rows written before this column
            // existed read as the 10-day buffer that was in force at the time.
            $table->unsignedInteger('days_of_coverage')->default(10)->after('lead_time');
            // Nullable like its sibling metrics: "—" stays distinguishable from 0.
            $table->decimal('po_qty', 14, 4)->nullable()->after('stocks_needed_for_lead_time');
        });

        // Bring rows written before this column up to the current formula. Both
        // figures come entirely from columns the row already stores, so this is the
        // same arithmetic the snapshot command would have done on the day — no
        // reading of today's transactions, which would corrupt a past date. The
        // backfilled buffer is 10 days for every row, which is what was in force:
        // the value was not configurable before the column existed.
        DB::table('inventory_item_snapshots')
            ->whereNull('po_qty')
            ->update([
                'po_qty' => DB::raw('days_of_coverage * three_days_average'),
                'po_needed' => DB::raw('GREATEST(0, (days_of_coverage * three_days_average) + COALESCE(stocks_needed_for_lead_time, 0) - COALESCE(remaining_after_fulfillment, 0))'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn(['days_of_coverage', 'po_qty']);
        });
    }
};

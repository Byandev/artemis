<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop three columns the module stopped needing, and two it never really used.
 *
 * inventory_item_snapshots.three_days_average was the frozen copy of a figure
 * that is now measured once, per item, as units_3d — the migration before this
 * one rebuilt units_3d from it, so the number survives in the column that
 * replaced it. Nothing reads the copy any more: the list derives its rate from
 * units_3d, and the export reads the query's alias rather than the table.
 *
 * The keyword columns go from both tables. They were a fallback spelling for
 * matching an ERP line to an item — the SKU always won, and the keyword only
 * caught a name spelled differently upstream. Neither has ever been filled on
 * this installation: 0 of 186 items carry either. Every path that consulted
 * them is removed with them, so nothing is left reading a column that is always
 * empty and silently falling through.
 *
 * The snapshot copies go for the same reason they were written — a snapshot row
 * mirrors the item, and an item no longer has these.
 *
 * down() puts the columns back empty. It cannot put the contents back: the
 * keywords are gone with the drop, and three_days_average would have to be
 * reconstructed from units_3d, which is the same number one division away but
 * not the same history. Take a dump first if that matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn(['three_days_average', 'sales_keywords', 'transaction_keywords']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['sales_keywords', 'transaction_keywords']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->decimal('three_days_average', 10, 4)->default(0)->after('unfulfilled_count');
            $table->text('sales_keywords')->nullable()->after('is_active');
            $table->text('transaction_keywords')->nullable()->after('sales_keywords');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->text('sales_keywords')->nullable();
            $table->text('transaction_keywords')->nullable();
        });
    }
};

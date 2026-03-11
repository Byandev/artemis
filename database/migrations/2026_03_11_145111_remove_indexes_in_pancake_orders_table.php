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
        $indexes = collect(DB::select('SHOW INDEX FROM pancake_orders'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        Schema::table('pancake_orders', function (Blueprint $table) use ($indexes) {
            if (in_array('po_page_id_idx', $indexes)) {
                $table->dropIndex('po_page_id_idx');
            }

            if (in_array('po_page_returning_at_idx', $indexes)) {
                $table->dropIndex('po_page_returning_at_idx');
            }

            if (in_array('po_page_delivered_shipped_idx', $indexes)) {
                $table->dropIndex('po_page_delivered_shipped_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            //
        });
    }
};

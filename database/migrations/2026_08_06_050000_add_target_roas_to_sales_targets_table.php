<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ROAS the day is judged on, alongside the sales amount. Nullable: a target
 * can still be about sales alone, and the ones created before this column
 * existed have no bar to clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            // Same precision as advertiser_performance_daily_records.roas, so the
            // goal and the actual compare without rounding surprises.
            $table->decimal('target_roas', 10, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropColumn('target_roas');
        });
    }
};

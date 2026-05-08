<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workspace_customer_activity_daily');
        Schema::dropIfExists('workspace_customer_facts');
        Schema::dropIfExists('workspace_daily_metrics_by_location');
        Schema::dropIfExists('workspace_daily_metrics_by_item');
        Schema::dropIfExists('workspace_daily_metrics_by_rider');
        Schema::dropIfExists('workspace_daily_metrics');

        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            foreach (['confirmed_count', 'delivered_count', 'returning_count', 'returned_count', 'delivered_amount', 'returning_amount', 'returned_amount', 'sum_delivery_attempts_delivered', 'sum_delivery_attempts_returned'] as $col) {
                if (Schema::hasColumn('pancake_user_pos_daily_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'rts_rate')) {
                $table->decimal('rts_rate', 8, 2)->default(0)->after('delivered');
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: the analytics rollup feature was removed and is not coming back in this form.
    }
};

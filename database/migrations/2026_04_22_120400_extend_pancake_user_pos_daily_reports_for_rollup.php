<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'confirmed_count')) {
                $table->unsignedInteger('confirmed_count')->default(0)->after('total_orders');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'delivered_count')) {
                $table->unsignedInteger('delivered_count')->default(0)->after('delivered');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'returning_count')) {
                $table->unsignedInteger('returning_count')->default(0)->after('delivered_count');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'returned_count')) {
                $table->unsignedInteger('returned_count')->default(0)->after('returning_count');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'delivered_amount')) {
                $table->decimal('delivered_amount', 18, 2)->default(0)->after('returned_count');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'returning_amount')) {
                $table->decimal('returning_amount', 18, 2)->default(0)->after('delivered_amount');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'returned_amount')) {
                $table->decimal('returned_amount', 18, 2)->default(0)->after('returning_amount');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'sum_delivery_attempts_delivered')) {
                $table->unsignedInteger('sum_delivery_attempts_delivered')->default(0)->after('returned_amount');
            }
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'sum_delivery_attempts_returned')) {
                $table->unsignedInteger('sum_delivery_attempts_returned')->default(0)->after('sum_delivery_attempts_delivered');
            }
        });

        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('pancake_user_pos_daily_reports', 'rts_rate')) {
                $table->dropColumn('rts_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_user_pos_daily_reports', 'rts_rate')) {
                $table->decimal('rts_rate', 8, 2)->default(0)->after('delivered');
            }
        });

        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            foreach (['confirmed_count', 'delivered_count', 'returning_count', 'returned_count', 'delivered_amount', 'returning_amount', 'returned_amount', 'sum_delivery_attempts_delivered', 'sum_delivery_attempts_returned'] as $col) {
                if (Schema::hasColumn('pancake_user_pos_daily_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

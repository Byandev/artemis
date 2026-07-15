<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            // Daily order count, reported alongside the existing daily metrics.
            $table->unsignedInteger('orders')->nullable()->after('record_date');

            // Month-to-date running totals. The ERP omits these on some payloads,
            // so every one is nullable.
            $table->decimal('date_to_month_sales', 15, 2)->nullable()->after('rts_amount');
            $table->unsignedInteger('date_to_month_orders')->nullable()->after('date_to_month_sales');
            $table->decimal('date_to_month_ad_spent', 15, 2)->nullable()->after('date_to_month_orders');
            $table->decimal('date_to_month_roas', 10, 2)->nullable()->after('date_to_month_ad_spent');

            // Month-to-date RTS breakdowns, one group per stage the ERP reports.
            foreach (['sales_order', 'parcel_status', 'shipped_out'] as $stage) {
                $table->decimal("date_to_month_{$stage}_rts_rate", 8, 2)->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_delivered")->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_returned")->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_for_return")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            $columns = [
                'orders',
                'date_to_month_sales',
                'date_to_month_orders',
                'date_to_month_ad_spent',
                'date_to_month_roas',
            ];

            foreach (['sales_order', 'parcel_status', 'shipped_out'] as $stage) {
                $columns[] = "date_to_month_{$stage}_rts_rate";
                $columns[] = "date_to_month_{$stage}_delivered";
                $columns[] = "date_to_month_{$stage}_returned";
                $columns[] = "date_to_month_{$stage}_for_return";
            }

            $table->dropColumn($columns);
        });
    }
};

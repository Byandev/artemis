<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Artemis source stores peso amounts (not counts) in the per-stage
 * month-to-date delivered/returned/for_return columns, so widen them from
 * unsignedInteger to decimal(15, 2). Gencys counts still fit (stored as x.00).
 */
return new class extends Migration
{
    private const STAGES = ['sales_order', 'parcel_status', 'shipped_out'];

    private const METRICS = ['delivered', 'returned', 'for_return'];

    public function up(): void
    {
        Schema::table('advertiser_performance_daily_records', function (Blueprint $table) {
            foreach (self::STAGES as $stage) {
                foreach (self::METRICS as $metric) {
                    $table->decimal("date_to_month_{$stage}_{$metric}", 15, 2)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('advertiser_performance_daily_records', function (Blueprint $table) {
            foreach (self::STAGES as $stage) {
                foreach (self::METRICS as $metric) {
                    $table->unsignedInteger("date_to_month_{$stage}_{$metric}")->nullable()->change();
                }
            }
        });
    }
};

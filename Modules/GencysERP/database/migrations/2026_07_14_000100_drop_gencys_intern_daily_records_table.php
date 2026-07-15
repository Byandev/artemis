<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire gencys_intern_daily_records: Gencys daily data now lands directly in
 * the unified advertiser_performance_daily_records table (source=gencys). down()
 * recreates the schema (data is not restored).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gencys_intern_daily_records');
    }

    public function down(): void
    {
        Schema::create('gencys_intern_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gencys_intern_id')->constrained('gencys_interns')->cascadeOnDelete();
            $table->date('record_date')->nullable();
            $table->unsignedInteger('orders')->nullable();
            $table->decimal('sales', 15, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();
            $table->decimal('ad_spent', 15, 2)->nullable();
            $table->decimal('rts_rate', 8, 2)->nullable();
            $table->unsignedInteger('delivered')->nullable();
            $table->decimal('delivered_amount', 15, 2)->nullable();
            $table->unsignedInteger('returned')->nullable();
            $table->decimal('returned_amount', 15, 2)->nullable();
            $table->decimal('date_to_month_sales', 15, 2)->nullable();
            $table->unsignedInteger('date_to_month_orders')->nullable();
            $table->decimal('date_to_month_ad_spent', 15, 2)->nullable();
            $table->decimal('date_to_month_roas', 10, 2)->nullable();

            foreach (['sales_order', 'parcel_status', 'shipped_out'] as $stage) {
                $table->decimal("date_to_month_{$stage}_rts_rate", 8, 2)->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_delivered")->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_returned")->nullable();
                $table->unsignedInteger("date_to_month_{$stage}_for_return")->nullable();
            }

            $table->timestamps();

            $table->unique(['workspace_id', 'gencys_intern_id', 'record_date'], 'gidr_ws_intern_date_unique');
            $table->index(['workspace_id', 'record_date'], 'gidr_ws_date_index');
        });
    }
};

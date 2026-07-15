<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertiser_performance_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Which pipeline produced the row.
            $table->string('source', 16); // 'gencys' | 'artemis'

            // Polymorphic advertiser: Gencys → Intern, Artemis → User. Custom
            // column names (advertiser_model as the morph "type").
            $table->string('advertiser_model');
            $table->unsignedBigInteger('advertiser_id');
            $table->string('advertiser_name')->nullable(); // denormalized for source-agnostic reads

            $table->date('date');

            // Core metrics. `sales` is the purchase value → ROAS = sales / ad_spent.
            $table->unsignedInteger('orders')->nullable();
            $table->decimal('sales', 15, 2)->nullable();
            $table->decimal('ad_spent', 15, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();

            // Delivery / RTS. `returning` is Pancake's in-flight-return count;
            // `returned` is the Gencys delivered/returned count.
            $table->unsignedInteger('delivered')->nullable();
            $table->decimal('delivered_amount', 15, 2)->nullable();
            $table->unsignedInteger('returned')->nullable();
            $table->decimal('returned_amount', 15, 2)->nullable();
            $table->unsignedInteger('returning')->nullable();
            $table->decimal('rts_rate', 8, 2)->nullable();

            // Gencys month-to-date snapshots (null for Artemis rows).
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

            $table->unique(
                ['workspace_id', 'source', 'advertiser_model', 'advertiser_id', 'date'],
                'apdr_unique',
            );
            $table->index(['workspace_id', 'source', 'date'], 'apdr_ws_source_date_index');
            $table->index(['advertiser_model', 'advertiser_id'], 'apdr_advertiser_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_performance_daily_records');
    }
};

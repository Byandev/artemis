<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_daily_metrics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workspace_id');
            $table->date('date');
            $table->unsignedBigInteger('page_id');

            $table->unsignedInteger('confirmed_count')->default(0);
            $table->unsignedInteger('shipped_count')->default(0);
            $table->unsignedInteger('first_delivery_attempt_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('returning_in_transit_count')->default(0);
            $table->unsignedInteger('returned_final_count')->default(0);
            $table->unsignedInteger('for_delivery_count')->default(0);

            $table->decimal('total_sales', 18, 2)->default(0);
            $table->decimal('delivered_amount', 18, 2)->default(0);
            $table->decimal('returning_amount', 18, 2)->default(0);
            $table->decimal('returned_amount', 18, 2)->default(0);

            $table->unsignedInteger('sum_delivery_attempts_delivered')->default(0);
            $table->unsignedInteger('sum_delivery_attempts_returned')->default(0);

            $table->decimal('sum_customer_rts_rate_delivered', 18, 4)->default(0);
            $table->unsignedInteger('count_customer_rts_rate_delivered')->default(0);
            $table->decimal('sum_customer_rts_rate_returned', 18, 4)->default(0);
            $table->unsignedInteger('count_customer_rts_rate_returned')->default(0);

            $table->unsignedInteger('sum_days_confirmed_to_shipped')->default(0);
            $table->unsignedInteger('count_confirmed_to_shipped')->default(0);
            $table->unsignedInteger('sum_days_confirmed_to_first_attempt')->default(0);
            $table->unsignedInteger('count_confirmed_to_first_attempt')->default(0);
            $table->unsignedInteger('sum_days_confirmed_to_delivered')->default(0);
            $table->unsignedInteger('count_confirmed_to_delivered')->default(0);
            $table->unsignedInteger('sum_days_shipped_to_first_attempt')->default(0);
            $table->unsignedInteger('count_shipped_to_first_attempt')->default(0);
            $table->unsignedInteger('sum_days_shipped_to_delivered')->default(0);
            $table->unsignedInteger('count_shipped_to_delivered')->default(0);
            $table->unsignedInteger('sum_days_returning_to_returned')->default(0);
            $table->unsignedInteger('count_returning_to_returned')->default(0);

            $table->unsignedInteger('tracked_orders_count')->default(0);
            $table->unsignedInteger('sms_sent_count')->default(0);
            $table->unsignedInteger('chat_sent_count')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'date', 'page_id'], 'wdm_workspace_date_page_unique');
            $table->index(['workspace_id', 'date'], 'wdm_workspace_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_daily_metrics');
    }
};

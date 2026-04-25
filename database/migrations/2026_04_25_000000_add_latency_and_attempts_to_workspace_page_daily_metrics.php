<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->bigInteger('sum_days_confirmed_to_shipped')->default(0)->after('returned_amount');
            $table->unsignedInteger('count_confirmed_to_shipped')->default(0)->after('sum_days_confirmed_to_shipped');

            $table->bigInteger('sum_days_confirmed_to_first_attempt')->default(0)->after('count_confirmed_to_shipped');
            $table->unsignedInteger('count_confirmed_to_first_attempt')->default(0)->after('sum_days_confirmed_to_first_attempt');

            $table->bigInteger('sum_days_confirmed_to_delivered')->default(0)->after('count_confirmed_to_first_attempt');
            $table->unsignedInteger('count_confirmed_to_delivered')->default(0)->after('sum_days_confirmed_to_delivered');

            $table->bigInteger('sum_days_shipped_to_first_attempt')->default(0)->after('count_confirmed_to_delivered');
            $table->unsignedInteger('count_shipped_to_first_attempt')->default(0)->after('sum_days_shipped_to_first_attempt');

            $table->bigInteger('sum_days_shipped_to_delivered')->default(0)->after('count_shipped_to_first_attempt');
            $table->unsignedInteger('count_shipped_to_delivered')->default(0)->after('sum_days_shipped_to_delivered');

            $table->bigInteger('sum_days_returning_to_returned')->default(0)->after('count_shipped_to_delivered');
            $table->unsignedInteger('count_returning_to_returned')->default(0)->after('sum_days_returning_to_returned');

            $table->bigInteger('sum_delivery_attempts_delivered')->default(0)->after('count_returning_to_returned');
            $table->unsignedInteger('count_delivery_attempts_delivered')->default(0)->after('sum_delivery_attempts_delivered');

            $table->bigInteger('sum_delivery_attempts_returned')->default(0)->after('count_delivery_attempts_delivered');
            $table->unsignedInteger('count_delivery_attempts_returned')->default(0)->after('sum_delivery_attempts_returned');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'sum_days_confirmed_to_shipped',
                'count_confirmed_to_shipped',
                'sum_days_confirmed_to_first_attempt',
                'count_confirmed_to_first_attempt',
                'sum_days_confirmed_to_delivered',
                'count_confirmed_to_delivered',
                'sum_days_shipped_to_first_attempt',
                'count_shipped_to_first_attempt',
                'sum_days_shipped_to_delivered',
                'count_shipped_to_delivered',
                'sum_days_returning_to_returned',
                'count_returning_to_returned',
                'sum_delivery_attempts_delivered',
                'count_delivery_attempts_delivered',
                'sum_delivery_attempts_returned',
                'count_delivery_attempts_returned',
            ]);
        });
    }
};

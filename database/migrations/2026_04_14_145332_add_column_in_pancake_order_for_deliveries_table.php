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
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unsignedInteger('customer_call_attempts')->default(0);
            $table->unsignedInteger('customer_call_duration')->default(0);
            $table->timestamp('customer_last_call')->nullable();
            $table->unsignedInteger('rider_call_attempts')->default(0);
            $table->unsignedInteger('rider_call_duration')->default(0);
            $table->timestamp('rider_last_call')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropColumn('customer_call_attempts');
            $table->dropColumn('customer_call_duration');
            $table->dropColumn('customer_last_call');
            $table->dropColumn('rider_call_attempts');
            $table->dropColumn('rider_call_duration');
            $table->dropColumn('rider_last_call');
        });
    }
};

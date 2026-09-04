<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A CSR's calls for a day, per shop.
     *
     * Counts the calls themselves, split the ways the phone actually splits:
     * RMO calls against order-verification ones, and within RMO, the customer
     * against the rider — with the day's deliveries beside them, so a row says
     * both what the CSR was given and what they did about it.
     *
     * Every figure is a count and its seconds, so a card can state "how many"
     * and "how long" from one row without a second query.
     */
    public function up(): void
    {
        Schema::create('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('pancake_user_id');
            // The shop the calls' orders belong to. No foreign key: shops are
            // synced from Pancake and rows come and go.
            $table->unsignedBigInteger('shop_id');
            $table->date('date');

            // Every call in the row, however it was placed.
            $table->unsignedInteger('total_called')->default(0);
            $table->unsignedInteger('total_call_time')->default(0);

            // The calls about a delivery — the ones carrying an order.
            $table->unsignedInteger('total_rmo_called')->default(0);
            $table->unsignedInteger('total_rmo_call_time')->default(0);

            // That same set split by who answered.
            $table->unsignedInteger('total_rmo_customer_called')->default(0);
            $table->unsignedInteger('total_rmo_customer_call_time')->default(0);
            $table->unsignedInteger('total_rmo_rider_called')->default(0);
            $table->unsignedInteger('total_rmo_rider_call_time')->default(0);

            // The deliveries behind those calls: what was put in the CSR's hands
            // that day, and what they confirmed as out for delivery. Every
            // assigned delivery counts, PENDING or not — this is the work given,
            // not the work done.
            $table->unsignedInteger('total_rmo_assigned_count')->default(0);
            $table->unsignedInteger('total_rmo_confirmed_count')->default(0);

            // Calls about an order but no delivery — verifying an order rather
            // than chasing a parcel.
            $table->unsignedInteger('total_verification_called')->default(0);
            $table->unsignedInteger('total_verification_call_time')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'pancake_user_id', 'shop_id', 'date'], 'pucall_ws_user_shop_date_unique');
            $table->index(['workspace_id', 'date'], 'pucall_workspace_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_user_daily_call_reports');
    }
};

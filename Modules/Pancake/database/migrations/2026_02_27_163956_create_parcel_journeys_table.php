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
        Schema::dropIfExists('parcel_journey_notifications');
        Schema::dropIfExists('parcel_journeys');

        Schema::create('parcel_journeys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('status');
            $table->text('rider_name')->nullable();
            $table->text('rider_mobile')->nullable();
            $table->text('note');
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('pancake_orders')
                ->onDelete('cascade');

            $table->index('order_id', 'idx_parcel_journeys_order_id');
            $table->index(['status', 'created_at'], 'idx_parcel_journeys_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_journeys');
    }
};

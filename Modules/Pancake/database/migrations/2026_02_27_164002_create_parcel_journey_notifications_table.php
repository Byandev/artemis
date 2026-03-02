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

        Schema::create('parcel_journey_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('parcel_journey_id');
            $table->enum('type', ['sms', 'chat']);
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('receiver_name');
            $table->string('receiver_identity');
            $table->text('message');
            $table->text('remarks')->nullable();
            $table->string('sms_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('pancake_orders')
                ->onDelete('cascade');

            $table->foreign('parcel_journey_id')
                ->references('id')
                ->on('parcel_journeys')
                ->onDelete('cascade');

            $table->index('order_id', 'idx_parcel_journey_notifications_order_id');

            // Indexes for filtering and sorting
            $table->index('created_at', 'idx_parcel_journey_notifications_created_at');
            $table->index('type', 'idx_parcel_journey_notifications_type');
            $table->index('status', 'idx_parcel_journey_notifications_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_journey_notifications');
    }
};

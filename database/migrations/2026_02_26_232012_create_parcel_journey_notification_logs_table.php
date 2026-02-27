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
        Schema::create('parcel_journey_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('page_id');
            $table->unsignedInteger('sms_sent')->default(0);
            $table->unsignedInteger('chat_sent')->default(0);
            $table->unsignedInteger('tracked_orders')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_journey_notification_logs');
    }
};

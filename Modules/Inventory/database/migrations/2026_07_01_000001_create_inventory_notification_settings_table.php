<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();

            // Where this workspace's inventory alerts are posted. Null falls back
            // to the INVENTORY_DISCORD_WEBHOOK_URL / DISCORD_WEBHOOK_URL env vars.
            $table->string('discord_webhook_url', 512)->nullable();

            // Daily "deliveries received" digest.
            $table->boolean('deliveries_enabled')->default(true);
            $table->string('deliveries_send_at', 5)->default('17:00'); // HH:MM

            // Daily "orders not yet delivered" alert.
            $table->boolean('awaiting_enabled')->default(true);
            $table->string('awaiting_send_at', 5)->default('10:00'); // HH:MM

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_notification_settings');
    }
};

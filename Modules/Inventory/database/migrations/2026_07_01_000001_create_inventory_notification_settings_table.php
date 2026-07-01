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
            $table->string('discord_webhook_url', 512)->nullable();
            $table->boolean('deliveries_enabled')->default(true);
            $table->string('deliveries_send_at', 5)->default('17:00');
            $table->boolean('awaiting_enabled')->default(true);
            $table->string('awaiting_send_at', 5)->default('10:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_notification_settings');
    }
};

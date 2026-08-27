<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            // Off by default: an existing workspace should not start posting to
            // Discord because it was upgraded.
            $table->boolean('discord_daily_stats_enabled')->default(false);
            $table->string('discord_webhook_url', 512)->nullable();
            // Whole hours only — the scheduler checks hourly, so a send time
            // with any other minute would never match.
            $table->string('discord_send_at', 5)->default('18:00');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn([
                'discord_daily_stats_enabled',
                'discord_webhook_url',
                'discord_send_at',
            ]);
        });
    }
};

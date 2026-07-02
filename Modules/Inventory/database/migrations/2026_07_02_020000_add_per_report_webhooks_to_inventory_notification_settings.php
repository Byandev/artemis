<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_notification_settings', function (Blueprint $table) {
            // Per-report webhook overrides. Each report posts to its own webhook,
            // falling back to the shared discord_webhook_url (then the env default).
            if (! Schema::hasColumn('inventory_notification_settings', 'deliveries_webhook_url')) {
                $table->string('deliveries_webhook_url', 512)->nullable()->after('discord_webhook_url');
            }
            if (! Schema::hasColumn('inventory_notification_settings', 'awaiting_webhook_url')) {
                $table->string('awaiting_webhook_url', 512)->nullable()->after('deliveries_webhook_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_notification_settings', function (Blueprint $table) {
            foreach (['deliveries_webhook_url', 'awaiting_webhook_url'] as $column) {
                if (Schema::hasColumn('inventory_notification_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

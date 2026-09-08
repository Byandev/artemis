<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('inactive_accounts_enabled')->default(true);
            $table->string('inactive_accounts_webhook_url', 512)->nullable();
            // HH:00 only — the report command runs hourly, so a non-zero minute
            // would never match and the report would silently never send.
            $table->string('inactive_accounts_send_at', 5)->default('09:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_notification_settings');
    }
};

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
        Schema::table('ads', function (Blueprint $table) {
            // Foreign key index for subquery JOINs (Product withAdSpent)
            $table->index('page_id', 'idx_ads_page_id');

            // Composite indexes for ad hierarchy filtering
            $table->index(['ad_account_id', 'campaign_id'], 'idx_ads_account_campaign');
            $table->index(['ad_account_id', 'ad_set_id'], 'idx_ads_account_adset');

            // Status index for filtering active/paused ads
            $table->index('status', 'idx_ads_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex('idx_ads_page_id');
            $table->dropIndex('idx_ads_account_campaign');
            $table->dropIndex('idx_ads_account_adset');
            $table->dropIndex('idx_ads_status');
        });
    }
};

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
        Schema::table('ad_records', function (Blueprint $table) {
            // Composite indexes for ad hierarchy with date filtering
            $table->index(['ad_account_id', 'date'], 'idx_ad_records_account_date');
            $table->index(['campaign_id', 'date'], 'idx_ad_records_campaign_date');
            $table->index(['ad_set_id', 'date'], 'idx_ad_records_adset_date');

            // Date index for ROAS and performance calculations
            $table->index('date', 'idx_ad_records_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_records', function (Blueprint $table) {
            $table->dropIndex('idx_ad_records_account_date');
            $table->dropIndex('idx_ad_records_campaign_date');
            $table->dropIndex('idx_ad_records_adset_date');
            $table->dropIndex('idx_ad_records_date');
        });
    }
};

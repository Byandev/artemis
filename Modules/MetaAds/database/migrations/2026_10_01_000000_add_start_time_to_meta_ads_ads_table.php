<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_ads', function (Blueprint $table) {
            // Meta has no ad-level start time, so SyncAds derives it: the later
            // of the ad's created_time and its ad set's start_time (see
            // Ad::deriveStartTime). Day 1 of the Launch Comparison for ads.
            $table->timestamp('start_time')->nullable()->after('created_time');
            $table->index(['meta_ads_account_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_ads', function (Blueprint $table) {
            $table->dropIndex(['meta_ads_account_id', 'start_time']);
            $table->dropColumn('start_time');
        });
    }
};

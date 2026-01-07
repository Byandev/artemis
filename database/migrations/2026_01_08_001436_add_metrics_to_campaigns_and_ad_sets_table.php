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
        // Add metrics to campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->decimal('impressions', 15, 2)->default(0)->after('status');
            $table->decimal('clicks', 15, 2)->default(0)->after('impressions');
            $table->decimal('spend', 15, 2)->default(0)->after('clicks');
            $table->decimal('conversions', 15, 2)->default(0)->after('spend');
            $table->decimal('ctr', 8, 4)->default(0)->after('conversions'); // Click-through rate
            $table->decimal('cpc', 15, 2)->default(0)->after('ctr'); // Cost per click
            $table->decimal('cpm', 15, 2)->default(0)->after('cpc'); // Cost per mille
        });

        // Add metrics to ad_sets table
        Schema::table('ad_sets', function (Blueprint $table) {
            $table->decimal('impressions', 15, 2)->default(0)->after('status');
            $table->decimal('clicks', 15, 2)->default(0)->after('impressions');
            $table->decimal('spend', 15, 2)->default(0)->after('clicks');
            $table->decimal('conversions', 15, 2)->default(0)->after('spend');
            $table->decimal('ctr', 8, 4)->default(0)->after('conversions');
            $table->decimal('cpc', 15, 2)->default(0)->after('ctr');
            $table->decimal('cpm', 15, 2)->default(0)->after('cpc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'clicks', 'spend', 'conversions', 'ctr', 'cpc', 'cpm']);
        });

        Schema::table('ad_sets', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'clicks', 'spend', 'conversions', 'ctr', 'cpc', 'cpm']);
        });
    }
};

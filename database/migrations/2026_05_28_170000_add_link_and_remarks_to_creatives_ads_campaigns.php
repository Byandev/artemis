<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives_ads_campaigns', function (Blueprint $table) {
            $table->string('ads_manager_link')->nullable()->after('ads_status');
            $table->text('remarks')->nullable()->after('ads_manager_link');
        });
    }

    public function down(): void
    {
        Schema::table('creatives_ads_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ads_manager_link', 'remarks']);
        });
    }
};

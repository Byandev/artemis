<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('sales_marketing_dashboard_module_enabled')->default(false)->after('meta_ads_module_enabled');
            $table->boolean('video_editor_dashboard_module_enabled')->default(false)->after('sales_marketing_dashboard_module_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['sales_marketing_dashboard_module_enabled', 'video_editor_dashboard_module_enabled']);
        });
    }
};

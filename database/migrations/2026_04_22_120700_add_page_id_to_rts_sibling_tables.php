<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_daily_metrics_by_rider', function (Blueprint $table) {
            if (! Schema::hasColumn('workspace_daily_metrics_by_rider', 'page_id')) {
                $table->unsignedBigInteger('page_id')->default(0)->after('rider_name');
            }
        });
        Schema::table('workspace_daily_metrics_by_rider', function (Blueprint $table) {
            $table->dropUnique('wdmbr_workspace_date_rider_unique');
            $table->unique(['workspace_id', 'date', 'rider_name', 'page_id'], 'wdmbr_workspace_date_rider_page_unique');
        });

        Schema::table('workspace_daily_metrics_by_item', function (Blueprint $table) {
            if (! Schema::hasColumn('workspace_daily_metrics_by_item', 'page_id')) {
                $table->unsignedBigInteger('page_id')->default(0)->after('item_name');
            }
        });
        Schema::table('workspace_daily_metrics_by_item', function (Blueprint $table) {
            $table->dropUnique('wdmbi_workspace_date_item_unique');
            $table->unique(['workspace_id', 'date', 'item_name', 'page_id'], 'wdmbi_workspace_date_item_page_unique');
        });

        Schema::table('workspace_daily_metrics_by_location', function (Blueprint $table) {
            if (! Schema::hasColumn('workspace_daily_metrics_by_location', 'page_id')) {
                $table->unsignedBigInteger('page_id')->default(0)->after('district_name');
            }
        });
        Schema::table('workspace_daily_metrics_by_location', function (Blueprint $table) {
            $table->dropUnique('wdmbl_workspace_date_loc_unique');
            $table->unique(['workspace_id', 'date', 'province_name', 'district_name', 'page_id'], 'wdmbl_workspace_date_loc_page_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_daily_metrics_by_location', function (Blueprint $table) {
            $table->dropUnique('wdmbl_workspace_date_loc_page_unique');
            $table->unique(['workspace_id', 'date', 'province_name', 'district_name'], 'wdmbl_workspace_date_loc_unique');
            $table->dropColumn('page_id');
        });

        Schema::table('workspace_daily_metrics_by_item', function (Blueprint $table) {
            $table->dropUnique('wdmbi_workspace_date_item_page_unique');
            $table->unique(['workspace_id', 'date', 'item_name'], 'wdmbi_workspace_date_item_unique');
            $table->dropColumn('page_id');
        });

        Schema::table('workspace_daily_metrics_by_rider', function (Blueprint $table) {
            $table->dropUnique('wdmbr_workspace_date_rider_page_unique');
            $table->unique(['workspace_id', 'date', 'rider_name'], 'wdmbr_workspace_date_rider_unique');
            $table->dropColumn('page_id');
        });
    }
};

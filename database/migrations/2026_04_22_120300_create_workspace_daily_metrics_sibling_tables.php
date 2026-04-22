<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_daily_metrics_by_rider', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->date('date');
            $table->string('rider_name');

            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('returning_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'date', 'rider_name'], 'wdmbr_workspace_date_rider_unique');
            $table->index(['workspace_id', 'rider_name'], 'wdmbr_workspace_rider_idx');
        });

        Schema::create('workspace_daily_metrics_by_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->date('date');
            $table->string('item_name');

            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('returning_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);
            $table->unsignedInteger('total_quantity')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'date', 'item_name'], 'wdmbi_workspace_date_item_unique');
            $table->index(['workspace_id', 'item_name'], 'wdmbi_workspace_item_idx');
        });

        Schema::create('workspace_daily_metrics_by_location', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->date('date');
            $table->string('province_name')->default('');
            $table->string('district_name')->default('');

            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('returning_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'date', 'province_name', 'district_name'], 'wdmbl_workspace_date_loc_unique');
            $table->index(['workspace_id', 'province_name'], 'wdmbl_workspace_province_idx');
        });

        DB::statement('CREATE FULLTEXT INDEX wdmbl_ft_location ON workspace_daily_metrics_by_location (province_name, district_name)');
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_daily_metrics_by_location');
        Schema::dropIfExists('workspace_daily_metrics_by_item');
        Schema::dropIfExists('workspace_daily_metrics_by_rider');
    }
};

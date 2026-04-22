<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_daily_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('workspace_daily_metrics', 'returning_in_transit_count')) {
                $table->renameColumn('returning_in_transit_count', 'returning_count');
            }
            if (Schema::hasColumn('workspace_daily_metrics', 'returned_final_count')) {
                $table->renameColumn('returned_final_count', 'returned_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspace_daily_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('workspace_daily_metrics', 'returning_count')) {
                $table->renameColumn('returning_count', 'returning_in_transit_count');
            }
            if (Schema::hasColumn('workspace_daily_metrics', 'returned_count')) {
                $table->renameColumn('returned_count', 'returned_final_count');
            }
        });
    }
};

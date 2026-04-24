<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_daily_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('workspace_daily_metrics', 'delivered_clean_count')) {
                $table->dropColumn('delivered_clean_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspace_daily_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('workspace_daily_metrics', 'delivered_clean_count')) {
                $table->unsignedInteger('delivered_clean_count')->default(0)->after('delivered_count');
            }
        });
    }
};

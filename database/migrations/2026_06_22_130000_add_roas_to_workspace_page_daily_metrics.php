<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            // Precomputed ROAS = tracked_sales / ad_spend (0 when no spend), so it
            // can be read directly without dividing.
            $table->decimal('roas', 10, 2)->default(0)->after('tracked_sales');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('roas');
        });
    }
};

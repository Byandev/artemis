<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The page's RTS over the 7 days ending on this one, filled by
     * `build-page-daily-performance`.
     */
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->decimal('previous_7_days_rts', 8, 2)->nullable()->after('rts_rate_30d');
        });
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn('previous_7_days_rts');
        });
    }
};

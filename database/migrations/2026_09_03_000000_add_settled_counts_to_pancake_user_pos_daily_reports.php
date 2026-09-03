<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The parcel counts behind `returning` / `delivered`, which are money.
        // SyncCsrDailyRecord already computed them; CSR analytics qualifies a
        // CSR on the count, so a parcel worth nothing still counts.
        //
        // Existing rows read zero until `sync:csr-daily-records --days=N` backfills.
        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            $table->unsignedInteger('returning_count')->default(0)->after('returning');
            $table->unsignedInteger('delivered_count')->default(0)->after('delivered');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_pos_daily_reports', function (Blueprint $table) {
            $table->dropColumn(['returning_count', 'delivered_count']);
        });
    }
};

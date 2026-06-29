<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_journey_notification_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('date');
        });

        // Roll the per-page aggregates up to the page's shop.
        DB::statement(<<<'SQL'
            UPDATE parcel_journey_notification_logs pjnl
            JOIN pages p ON p.id = pjnl.page_id
            SET pjnl.shop_id = p.shop_id
        SQL);

        Schema::table('parcel_journey_notification_logs', function (Blueprint $table) {
            $table->dropUnique(['page_id', 'date']);
            $table->dropColumn('page_id');
            $table->unique(['shop_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('parcel_journey_notification_logs', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'date']);
            $table->unsignedBigInteger('page_id')->nullable()->after('date');
        });

        // Reverse is lossy: attribute each shop's aggregate to one of its pages.
        DB::statement(<<<'SQL'
            UPDATE parcel_journey_notification_logs pjnl
            SET pjnl.page_id = (
                SELECT p.id FROM pages p
                WHERE p.shop_id = pjnl.shop_id
                ORDER BY p.id
                LIMIT 1
            )
        SQL);

        Schema::table('parcel_journey_notification_logs', function (Blueprint $table) {
            $table->dropColumn('shop_id');
            $table->unique(['page_id', 'date']);
        });
    }
};

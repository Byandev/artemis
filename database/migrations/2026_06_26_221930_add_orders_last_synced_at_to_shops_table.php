<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('orders_last_synced_at')->nullable()->after('avatar_url');
        });

        // Seed the shop-level watermark from the earliest page watermark so existing
        // shops keep syncing without re-pulling history, and aren't skipped by the
        // hourly trigger (which only picks shops with a non-null watermark).
        DB::table('shops')->update([
            'orders_last_synced_at' => DB::raw(
                '(select min(orders_last_synced_at) from pages '.
                'where pages.shop_id = shops.id and pages.orders_last_synced_at is not null)'
            ),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('orders_last_synced_at');
        });
    }
};

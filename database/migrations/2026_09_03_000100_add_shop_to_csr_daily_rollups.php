<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split the two CSR rollups by shop.
     *
     * A CSR's day was one row; it is now one row per shop they worked, so their
     * figures can be read per shop without going back to the orders. Every
     * existing reader groups by CSR already, so they keep reporting the same
     * totals — they just add up more rows.
     *
     * Rows written before this are left at shop 0 — the old grain under a new
     * key. Re-run `sync:csr-daily-records` and `sync:csr-rmo-daily-records` over
     * the range you care about to split them properly.
     */
    private const TABLES = [
        'pancake_user_pos_daily_reports' => ['pupos_workspace_user_date_unique', 'pupos_ws_user_shop_date_unique'],
        'pancake_user_rmo_daily_reports' => ['purmo_workspace_user_date_unique', 'purmo_ws_user_shop_date_unique'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$oldUnique, $newUnique]) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldUnique, $newUnique) {
                // Defaulted so the column can land on a table that already has
                // rows; the sync always writes a real shop id.
                $blueprint->unsignedBigInteger('shop_id')->default(0)->after('pancake_user_id');

                $blueprint->dropUnique($oldUnique);
                $blueprint->unique(['workspace_id', 'pancake_user_id', 'shop_id', 'date'], $newUnique);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [$oldUnique, $newUnique]) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldUnique, $newUnique) {
                $blueprint->dropUnique($newUnique);
                $blueprint->dropColumn('shop_id');
                $blueprint->unique(['workspace_id', 'pancake_user_id', 'date'], $oldUnique);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The courier fee on a pancake order, so a non-gencys workspace's statement
     * can carry a shipping figure the way a gencys one does.
     *
     * Nullable and guarded: the column may already have been added by hand, and
     * a null reads as nothing rather than as a zero fee.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pancake_orders', 'shipping_fee')) {
            return;
        }

        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 12, 2)->nullable()->after('discount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pancake_orders', 'shipping_fee')) {
            return;
        }

        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};

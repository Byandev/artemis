<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webcake orders (order source -7) arrive with no page_id, so page_id must be
     * nullable. The unique key is realigned to (order_number, shop_id, workspace_id)
     * — the same keys UpsertOrderAction::updateOrCreate matches on — because the old
     * page_id-based key can't dedupe orders whose page_id is null.
     */
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable()->change();
        });

        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropUnique('pancake_orders_order_number_page_id_workspace_id_unique');
            $table->unique(['order_number', 'shop_id', 'workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropUnique(['order_number', 'shop_id', 'workspace_id']);
            $table->unique(['order_number', 'page_id', 'workspace_id']);
        });

        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable(false)->change();
        });
    }
};

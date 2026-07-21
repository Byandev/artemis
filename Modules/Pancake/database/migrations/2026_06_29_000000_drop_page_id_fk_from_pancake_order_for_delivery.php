<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pancake can deliver orders for pages we haven't synced locally, so for-delivery
     * rows may carry a page_id with no matching `pages` row. The FK constraint rejects
     * those inserts (SQLSTATE 23000 / errno 1452), so drop it and keep page_id as a
     * plain nullable column. The supporting indexes on page_id stay in place.
     */
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropForeign('pancake_order_for_delivery_page_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->cascadeOnDelete();
        });
    }
};

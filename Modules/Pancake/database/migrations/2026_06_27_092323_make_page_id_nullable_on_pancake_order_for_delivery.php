<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Page-less Webcake orders produce for-delivery rows with no page, so page_id must
     * be nullable. MySQL won't alter an FK-referenced column in place, so drop the FK,
     * change the column, then re-add the FK (it still permits NULLs).
     */
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropForeign('pancake_order_for_delivery_page_id_foreign');
        });

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable()->change();
        });

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropForeign('pancake_order_for_delivery_page_id_foreign');
        });

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable(false)->change();
        });

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->cascadeOnDelete();
        });
    }
};

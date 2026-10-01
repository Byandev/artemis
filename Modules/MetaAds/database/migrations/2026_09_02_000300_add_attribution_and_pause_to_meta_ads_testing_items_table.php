<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two additions to a tracked item.
     *
     * Attribution: which ad account it runs on, and which product it sells.
     * The account comes straight off the campaign / ad set. The product is
     * derived — ad set → meta_page_id → page → shop → shops.product_id, the
     * same path the budget tracker uses — so it is nullable and re-resolved on
     * every sync, filling itself in if the page/shop link lands later.
     *
     * Pause: a paused item keeps its history but stops accruing new days.
     */
    public function up(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_ads_account_id')->nullable()->after('item_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('meta_ads_account_id');
            $table->timestamp('paused_at')->nullable()->after('product_id');

            $table->index('meta_ads_account_id', 'mati_account_index');
            $table->index('product_id', 'mati_product_index');

            $table->foreign('product_id', 'mati_product_fk')
                ->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->dropForeign('mati_product_fk');
            $table->dropIndex('mati_account_index');
            $table->dropIndex('mati_product_index');
            $table->dropColumn(['meta_ads_account_id', 'product_id', 'paused_at']);
        });
    }
};

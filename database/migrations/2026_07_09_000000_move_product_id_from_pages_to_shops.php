<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('workspace_id');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();

            // Mirrors the old idx_pages_product_id — analytics now join on shops.product_id.
            $table->index('product_id', 'idx_shops_product_id');
        });

        // A product is now assigned to a shop (one product per shop). Carry over the
        // existing page-level link: each shop adopts the product from the first of
        // its pages that has one.
        DB::statement(<<<'SQL'
            UPDATE shops s
            SET product_id = (
                SELECT p.product_id
                FROM pages p
                WHERE p.shop_id = s.id
                  AND p.product_id IS NOT NULL
                ORDER BY p.id
                LIMIT 1
            )
        SQL);

        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            // Indexes added in 2026_01_07_105726_add_performance_indexes_to_pages_table.
            $table->dropIndex('idx_pages_product_id');
            $table->dropIndex('idx_pages_workspace_product');
            $table->dropColumn('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('shop_id');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            // Restore the indexes exactly as they were so the earlier index
            // migration's down() can still drop them by name.
            $table->index(['workspace_id', 'product_id'], 'idx_pages_workspace_product');
            $table->index('product_id', 'idx_pages_product_id');
        });

        // Reverse: every page inherits the product assigned to its shop.
        DB::statement(<<<'SQL'
            UPDATE pages p
            JOIN shops s ON s.id = p.shop_id
            SET p.product_id = s.product_id
        SQL);

        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex('idx_shops_product_id');
            $table->dropColumn('product_id');
        });
    }
};

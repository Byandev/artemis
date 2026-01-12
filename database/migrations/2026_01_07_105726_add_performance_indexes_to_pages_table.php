<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Composite indexes for workspace filtering with related entities
            $table->index(['workspace_id', 'owner_id'], 'idx_pages_workspace_owner');
            $table->index(['workspace_id', 'shop_id'], 'idx_pages_workspace_shop');
            $table->index(['workspace_id', 'product_id'], 'idx_pages_workspace_product');

            // Foreign key indexes for JOIN operations and subqueries
            $table->index('product_id', 'idx_pages_product_id');
            $table->index('owner_id', 'idx_pages_owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('idx_pages_workspace_owner');
            $table->dropIndex('idx_pages_workspace_shop');
            $table->dropIndex('idx_pages_workspace_product');
            $table->dropIndex('idx_pages_product_id');
            $table->dropIndex('idx_pages_owner_id');
        });
    }
};

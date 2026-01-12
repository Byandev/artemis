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
        Schema::table('products', function (Blueprint $table) {
            // Composite indexes for workspace filtering with status and sorting
            $table->index(['workspace_id', 'status'], 'idx_products_workspace_status');
            $table->index(['workspace_id', 'created_at'], 'idx_products_workspace_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_workspace_status');
            $table->dropIndex('idx_products_workspace_created');
        });
    }
};

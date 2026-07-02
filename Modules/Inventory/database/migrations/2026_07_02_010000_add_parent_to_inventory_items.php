<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so the migration is safe to re-run where the columns already exist.
        if (Schema::hasColumn('inventory_items', 'parent_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            // Groups SKU variants of the same product (e.g. same item from
            // different suppliers) under one placeholder "parent" item. A parent
            // is itself an inventory_items row with is_parent = true and holds no
            // stock of its own — its children carry the real supplier SKUs.
            $table->unsignedBigInteger('parent_id')->nullable()->after('product_id');
            $table->boolean('is_parent')->default(false)->after('parent_id');

            // Deleting a parent ungroups its children rather than deleting them.
            $table->foreign('parent_id')
                ->references('id')
                ->on('inventory_items')
                ->nullOnDelete();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_items', 'parent_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'is_parent']);
        });
    }
};

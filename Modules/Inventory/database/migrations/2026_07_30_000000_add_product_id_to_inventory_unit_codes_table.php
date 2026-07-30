<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a unit code to a product directly, so a unit code (and the orders that
 * carry it) can resolve to a real product instead of relying on the free-text
 * order name. Nullable while existing rows are backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_unit_codes', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('sku')
                ->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_unit_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};

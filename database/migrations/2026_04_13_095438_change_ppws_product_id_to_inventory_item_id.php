<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_ppws', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->foreignId('inventory_item_id')->after('id')->constrained('inventory_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ppws', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn('inventory_item_id');
            $table->foreignId('product_id')->after('id')->constrained()->onDelete('cascade');
        });
    }
};

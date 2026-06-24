<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_unit_code_inventory_items', function (Blueprint $table) {
            $table->id();
            // Explicit short FK name: the default would exceed MySQL's 64-char
            // identifier limit given the long table name.
            $table->foreignId('gencys_unit_code_id')
                ->constrained('gencys_unit_codes', indexName: 'guc_items_unit_code_id_fk')
                ->cascadeOnDelete();

            $table->string('unit_code')->nullable();
            $table->string('inventory_item_code')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('price', 12, 2)->nullable();

            $table->timestamps();

            $table->index('unit_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_unit_code_inventory_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_unit_code_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            // Linked to its parent unit code by (workspace_id, unit_code) rather
            // than a foreign id, to keep the table self-describing and generic.
            $table->string('unit_code');
            $table->string('item_code');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'unit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_code_items');
    }
};

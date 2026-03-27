<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('ref_no')->nullable();
            $table->integer('po_qty_in')->default(0);
            $table->integer('po_qty_out')->default(0);
            $table->integer('rts_goods_out')->default(0);
            $table->integer('rts_goods_in')->default(0);
            $table->integer('rts_bad')->default(0);
            $table->integer('remaining_qty')->default(0);
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions_table');
    }
};

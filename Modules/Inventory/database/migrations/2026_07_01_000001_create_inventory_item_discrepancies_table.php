<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->date('date');
            // The physical count the user typed. Kept for the audit trail so a row is
            // self-describing (what was counted, not just the derived offset).
            $table->integer('counted_qty')->nullable();
            // Signed offset = counted_qty - ledger stock at count time. The latest row
            // (by date, then id) is layered onto the item's remaining stock on read.
            $table->integer('discrepancy');
            $table->timestamps();

            $table->index(['inventory_item_id', 'date']);
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_discrepancies');
    }
};

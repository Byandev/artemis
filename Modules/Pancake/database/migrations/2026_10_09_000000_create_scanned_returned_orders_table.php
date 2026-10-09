<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every order the RTS Scanner app scanned as returned, and whether Pancake
 * POS took the "returned" status. Rows that stay unsynced are the ones to
 * retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_returned_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // No foreign keys to shops / pancake_orders: both are synced from Pancake.
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            // Copied from the order, so the log still reads if the order is deleted.
            $table->string('order_number')->nullable();
            $table->string('tracking_code');
            $table->boolean('synced_to_pancake')->default(false);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['workspace_id', 'synced_to_pancake']);
            $table->index(['workspace_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_returned_orders');
    }
};

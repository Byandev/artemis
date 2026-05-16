<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pancake_returned_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('pancake_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->string('tracking_code');
            $table->string('order_number')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['workspace_id', 'shop_id']);
            $table->index(['workspace_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_returned_orders');
    }
};
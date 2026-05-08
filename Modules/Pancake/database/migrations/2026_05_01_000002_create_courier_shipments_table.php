<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pancake_order_id')->nullable()
                ->constrained('pancake_orders')
                ->nullOnDelete();

            $table->string('courier')->default('jt');
            $table->string('waybill_no');
            $table->string('order_number')->nullable();
            $table->string('order_status')->nullable();
            $table->string('creator_code')->nullable();

            $table->string('receiver')->nullable();
            $table->string('receiver_cellphone')->nullable();

            $table->decimal('cod', 15, 2)->nullable();
            $table->string('pouches_size')->nullable();
            $table->unsignedInteger('print_number')->nullable();
            $table->date('preferred_pickup_date')->nullable();

            $table->string('shipping_customer')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_cellphone')->nullable();
            $table->string('sender_province')->nullable();
            $table->string('sender_city')->nullable();
            $table->string('sender_address')->nullable();

            $table->string('item_name')->nullable();
            $table->decimal('item_weight', 10, 3)->nullable();
            $table->unsignedInteger('number_of_items')->nullable();

            $table->decimal('cod_fee', 15, 2)->nullable();
            $table->decimal('receivable_freight', 15, 2)->nullable();
            $table->decimal('total_shipping_cost', 15, 2)->nullable();
            $table->decimal('item_value', 15, 2)->nullable();
            $table->decimal('valuation_fee', 15, 2)->nullable();

            $table->string('rts_reason')->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'courier', 'waybill_no']);
            $table->index('waybill_no');
            $table->index(['workspace_id', 'preferred_pickup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_shipments');
    }
};

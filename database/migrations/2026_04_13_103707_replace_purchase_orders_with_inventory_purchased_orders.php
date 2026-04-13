<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::enableForeignKeyConstraints();

        // Create new inventory purchased orders table
        Schema::dropIfExists('inventory_purchased_order_items');
        Schema::dropIfExists('inventory_purchased_orders');
        Schema::create('inventory_purchased_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->date('issue_date');
            $table->string('delivery_no')->nullable();
            $table->string('cust_po_no')->nullable();
            $table->string('control_no')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        // Create new inventory purchased order items table
        Schema::create('inventory_purchased_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_purchased_order_id');
            $table->foreign('inventory_purchased_order_id', 'ipo_items_order_id_foreign')
                  ->references('id')->on('inventory_purchased_orders')->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->integer('count')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('inventory_purchased_order_items');
        Schema::dropIfExists('inventory_purchased_orders');
        Schema::enableForeignKeyConstraints();

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->date('issued_at');
            $table->date('delivered_at')->nullable();
            $table->float('total_amount');
            $table->float('delivery_amount');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->float('amount');
            $table->timestamps();
        });
    }
};

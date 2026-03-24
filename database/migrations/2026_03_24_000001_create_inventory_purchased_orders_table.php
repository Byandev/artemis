<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_purchased_orders')) {
            return;
        }

        Schema::create('inventory_purchased_orders', function (Blueprint $table) {
            $table->id();
            $table->date('issue_date')->index();
            $table->string('delivery_no')->nullable();
            $table->string('cust_po_no')->nullable();
            $table->string('control_no')->nullable();
            $table->string('item');
            $table->decimal('cog_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchased_orders');
    }
};

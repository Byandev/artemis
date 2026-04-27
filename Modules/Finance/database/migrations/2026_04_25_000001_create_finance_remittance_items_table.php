<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_remittance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remittance_id')->constrained('finance_remittances')->cascadeOnDelete();
            $table->string('waybill_number');
            $table->string('order_number')->nullable();
            $table->dateTime('shipping_date')->nullable();
            $table->string('sender_city')->nullable();
            $table->string('destination_city')->nullable();
            $table->decimal('package_billing_weight', 10, 2)->default(0);
            $table->decimal('item_value', 12, 2)->default(0);
            $table->decimal('value_added_fee', 12, 2)->default(0);
            $table->decimal('receivable_freight', 12, 2)->default(0);
            $table->decimal('total_shipping_cost', 12, 2)->default(0);
            $table->decimal('cod', 12, 2)->default(0);
            $table->decimal('cod_commission_rate', 6, 2)->default(0);
            $table->decimal('cod_commission', 12, 2)->default(0);
            $table->decimal('cod_commission_vat_fee', 12, 2)->default(0);
            $table->string('shipping_customer_code')->nullable();
            $table->dateTime('signing_time')->nullable();
            $table->timestamps();

            $table->index('remittance_id');
            $table->index('waybill_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_remittance_items');
    }
};

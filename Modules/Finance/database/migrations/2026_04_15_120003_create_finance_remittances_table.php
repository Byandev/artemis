<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('courier')->default('J&T');
            $table->string('soa_number');
            $table->date('billing_date_from');
            $table->date('billing_date_to');
            $table->decimal('gross_cod', 12, 2);
            $table->decimal('cod_fee', 12, 2);
            $table->decimal('cod_fee_vat', 12, 2);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('return_shipping', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->enum('status', ['pending', 'remitted'])->default('pending');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'soa_number']);
            $table->index(['workspace_id', 'status']);
            $table->foreign('transaction_id')
                ->references('id')->on('finance_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_remittances');
    }
};

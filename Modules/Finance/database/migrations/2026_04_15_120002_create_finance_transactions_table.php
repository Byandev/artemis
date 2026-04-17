<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained('finance_accounts')
                ->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->enum('type', ['in', 'out']);
            $table->enum('transaction_type', ['funds', 'profit_share', 'expenses', 'transfer', 'remittance'])->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('running_balance', 15, 2)->nullable();
            $table->enum('sub_category', [
                'ad_spent', 'cogs', 'subscription', 'shipping_fee',
                'operation_expense', 'salary', 'transfer_fee', 'seminar_fee', 'others',
            ])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'date', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
    }
};

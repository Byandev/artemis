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
            $table->enum('transaction_type', ['funds', 'profit_share', 'expenses', 'transfer', 'remittance'])->default('funds');
            $table->decimal('amount', 15, 2);
            $table->enum('category', ['remittance', 'expense', 'transfer', 'other'])->default('other');
            $table->foreignId('remittance_id')
                ->nullable()
                ->constrained('finance_remittances')
                ->nullOnDelete();
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

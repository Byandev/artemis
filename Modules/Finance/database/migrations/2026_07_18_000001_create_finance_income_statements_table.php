<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_income_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // First day of the month the statement covers.
            $table->date('period_month');

            // Snapshot totals, frozen at generate time.
            $table->decimal('total_delivered', 15, 2)->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);

            $table->string('status')->default('final');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // One statement per workspace per month.
            $table->unique(['workspace_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_statements');
    }
};

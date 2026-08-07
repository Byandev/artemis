<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-user, per-product commission rate. On the per-user income-statement
     * product breakdown each product that nets a positive profit earns the intern
     * a commission of `rate` × that product's net profit. The commission is shown
     * for information only — it does not change the statement's profit figures.
     */
    public function up(): void
    {
        Schema::create('finance_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Fraction of the product's net profit, e.g. 0.05 = 5%.
            $table->decimal('rate', 6, 4)->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_commission_rates');
    }
};

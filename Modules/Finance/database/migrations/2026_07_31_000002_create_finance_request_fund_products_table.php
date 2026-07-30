<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The products a fund request covers, each bearing a share of the amount
     * requested. Available on every template — the Ad Spent line items describe
     * the ad budget maths, this describes who the money is for.
     */
    public function up(): void
    {
        Schema::create('finance_request_fund_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_request_funds')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Name snapshot: the row must still read correctly after the product
            // it points at is gone, and it is the value handed to a transaction's
            // free-text product tags when one is filled in from this request.
            $table->string('product_label', 191);
            // This product's share of the amount requested. The shares of a
            // request always add up to that amount.
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['fund_request_id', 'product_id']);
            $table->index(['fund_request_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_fund_products');
    }
};

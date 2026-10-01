<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The line items a fund request is for. Each row's amount is its quantity
     * times its unit price, and the rows sum to the request's amount_requested.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_particulars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_fund_requests', indexName: 'fund_req_particular_request_foreign')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fund_request_id', 'sort_order'], 'fund_req_particular_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_particulars');
    }
};

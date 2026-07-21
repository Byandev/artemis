<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_fund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_request_funds')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // The page the budget was read from. Nullable so a request survives
            // the page being archived, and so a product with no page assigned to
            // the requester can still be requested with a typed-in budget.
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            // Name snapshot: the row must still read correctly after the product
            // or page it points at is gone.
            $table->string('item_label');
            $table->unsignedInteger('creatives_running')->default(0);
            $table->decimal('budget_per_day', 15, 2)->default(0);
            $table->decimal('days', 8, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fund_request_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_fund_items');
    }
};

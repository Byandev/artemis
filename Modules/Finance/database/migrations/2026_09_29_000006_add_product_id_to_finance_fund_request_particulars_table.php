<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The product an ad-spend particular is for. Picked in place of typing a
     * name on ad-spend requests, and left empty on every other kind; `name`
     * keeps a snapshot so the row still reads once the product is deleted.
     */
    public function up(): void
    {
        Schema::table('finance_fund_request_particulars', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('fund_request_id')
                ->constrained('products', indexName: 'fund_req_particular_product_foreign')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_request_particulars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};

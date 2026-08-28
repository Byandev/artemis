<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The courier's COD fee on a product's delivered revenue, and the VAT on
     * that fee.
     *
     * Both are derived — the fee is a percentage of `delivered_amount` and the
     * VAT a percentage of the fee — but they are stored rather than recomputed
     * on read, because the rates they were struck at live on the parent
     * statement and can be changed afterwards. Storing them keeps a closed
     * statement showing the figures it was closed with.
     */
    public function up(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->decimal('cod_fee', 14, 2)->default(0)->after('delivered_amount');
            $table->decimal('cod_fee_vat', 14, 2)->default(0)->after('cod_fee');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->dropColumn(['cod_fee', 'cod_fee_vat']);
        });
    }
};

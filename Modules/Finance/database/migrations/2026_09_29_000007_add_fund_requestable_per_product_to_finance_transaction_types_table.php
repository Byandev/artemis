<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a fund request of the type is split by product line by line:
     * each particular picks a product, and the product shares are worked out
     * from them instead of being allocated by hand. Off for every type until
     * switched on.
     */
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->boolean('fund_requestable_per_product')->default(false)->after('fund_requestable');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('fund_requestable_per_product');
        });
    }
};

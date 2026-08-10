<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            // The account nature of this type: a `debit` normal balance
            // (expenses, assets) or a `credit` normal balance (income,
            // liabilities, equity). Defaults to debit — most types are expenses.
            $table->enum('nature', ['debit', 'credit'])->default('debit')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            // Rates used to compute COD Fee / VAT on this statement, snapshotted so
            // a finalized month stays reproducible even if the workspace defaults
            // change later. Stored as fractions (0.02 = 2%).
            $table->decimal('cod_fee_rate', 6, 4)->default(0.02)->after('net_profit');
            $table->decimal('vat_rate', 6, 4)->default(0.12)->after('cod_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn(['cod_fee_rate', 'vat_rate']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Advisory share = a % of Gross Profit deducted (with OPEX) to reach Net
        // Profit, applied only to gencys-partner workspaces. Rate snapshotted so a
        // later default change doesn't rewrite a closed statement.
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->decimal('advisory_rate', 6, 4)->default(0.30)->after('vat_rate');
            $table->decimal('advisory_share', 15, 2)->default(0)->after('advisory_rate');
        });

        Schema::table('finance_income_statement_settings', function (Blueprint $table) {
            $table->decimal('advisory_rate', 6, 4)->default(0.30)->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn(['advisory_rate', 'advisory_share']);
        });

        Schema::table('finance_income_statement_settings', function (Blueprint $table) {
            $table->dropColumn('advisory_rate');
        });
    }
};

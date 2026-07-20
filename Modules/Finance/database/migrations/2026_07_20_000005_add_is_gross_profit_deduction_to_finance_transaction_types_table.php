<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            // When true, this type is a Cost-of-Sales line on the income statement
            // (deducted to reach Gross Profit). When false (default) it is OPEX
            // (deducted after Gross Profit to reach Net Profit).
            $table->boolean('is_gross_profit_deduction')->default(false)->after('name');
        });

        // Pre-flag the obvious cost-of-sales types so behaviour matches what the
        // income statement did before this config existed. Users can adjust.
        DB::table('finance_transaction_types')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%ad spent%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%ad spend%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%cog%']);
            })
            ->update(['is_gross_profit_deduction' => true]);
    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('is_gross_profit_deduction');
        });
    }
};

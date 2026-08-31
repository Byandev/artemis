<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The statement's closing lines, added to every slice.
     *
     * OPEX is a company-wide pool — nothing in it is booked against one user or
     * one product — so a slice's share of it is allocated rather than measured:
     * each row takes the share matching its delivered orders. Net profit then
     * follows the workspace statement's own formula, gross after advisory less
     * that OPEX, so the three slices close out the way the parent does.
     *
     * Stored rather than derived at read time for the same reason every other
     * figure here is: a statement is a snapshot, and next month's OPEX must not
     * quietly rewrite last month's rows.
     */
    private const TABLES = [
        'finance_income_user_statements',
        'finance_income_product_statements',
        'finance_income_user_product_statements',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('opex', 14, 2)->default(0)
                    ->after('total_delivered_cogs');
                $table->decimal('net_profit_delivered_cogs', 14, 2)->default(0)
                    ->after('gross_profit_delivered_cogs_after_advisory_share');
                $table->decimal('net_profit_bought_cogs', 14, 2)->default(0)
                    ->after('gross_profit_bought_cogs_after_advisory_share');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([
                    'opex',
                    'net_profit_delivered_cogs',
                    'net_profit_bought_cogs',
                ]);
            });
        }
    }
};

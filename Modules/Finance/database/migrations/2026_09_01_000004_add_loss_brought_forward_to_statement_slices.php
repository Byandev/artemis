<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The carried deficit, and what a row comes to once it is filled, on every
     * slice — so the closing lines read the same at each grain as they do on
     * the statement itself.
     *
     * `loss_brought_forward` is one figure applied to both cost-of-goods bases:
     * it is entered by hand against a seller and a product, from books that
     * knew one bottom line, and splitting it two ways would invent a precision
     * the source never had. `cumulative_profit_*` still needs a column each,
     * since the net profit it comes off differs by basis.
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
                $table->decimal('loss_brought_forward', 14, 2)->default(0)
                    ->after('opex_share_percentage');
                $table->decimal('cumulative_profit_delivered_cogs', 14, 2)->default(0)
                    ->after('net_profit_delivered_cogs');
                $table->decimal('cumulative_profit_bought_cogs', 14, 2)->default(0)
                    ->after('net_profit_bought_cogs');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([
                    'loss_brought_forward',
                    'cumulative_profit_delivered_cogs',
                    'cumulative_profit_bought_cogs',
                ]);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The second way the advisory share can be struck: a percentage of delivered
     * revenue rather than of gross profit.
     *
     * Both are worked out and stored so the statement can show them side by side
     * and say which comes out lower — the figure is the same money either way,
     * and which basis applies is a matter of the agreement, not of the data.
     *
     * Workspace statement only for now; the per-user and per-product slices keep
     * the gross-profit basis alone.
     */
    public function up(): void
    {
        Schema::table('finance_income_statement_settings', function (Blueprint $table) {
            $table->decimal('advisory_delivered_rate', 6, 4)->default(0.09)->after('advisory_rate');
        });

        Schema::table('finance_income_statements', function (Blueprint $table) {
            // Snapshotted alongside the other rates, so a closed statement keeps
            // the terms it was struck under.
            $table->decimal('advisory_delivered_rate', 6, 4)->default(0.09)->after('advisory_rate');
            $table->decimal('advisory_share_on_delivered', 14, 2)->default(0)
                ->after('gross_profit_bought_cogs_advisory_share');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statement_settings', function (Blueprint $table) {
            $table->dropColumn('advisory_delivered_rate');
        });

        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn(['advisory_delivered_rate', 'advisory_share_on_delivered']);
        });
    }
};

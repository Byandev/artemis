<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatementSetting;
use Modules\Finance\Statements\Sources\PancakeOrderSource;

return new class extends Migration
{
    /**
     * Non-gencys workspaces ship with a different courier, which charges 2.75%
     * rather than 2%. New workspaces pick that up from the order source, but
     * ones that have already saved a rate need moving across.
     *
     * Only rows still sitting on the old default are touched — a workspace that
     * deliberately set some other rate keeps it. Statements already saved keep
     * the rate they were struck at, which is snapshotted on the statement, so
     * nothing closed is rewritten by this.
     */
    public function up(): void
    {
        DB::table('finance_income_statement_settings')
            ->whereIn('workspace_id', DB::table('workspaces')->where(
                fn ($q) => $q->whereNull('is_gencys_partner')->orWhere('is_gencys_partner', false)
            )->select('id'))
            ->where('cod_fee_rate', IncomeStatementSetting::DEFAULT_COD_FEE_RATE)
            ->update(['cod_fee_rate' => PancakeOrderSource::COD_FEE_RATE]);
    }

    public function down(): void
    {
        DB::table('finance_income_statement_settings')
            ->whereIn('workspace_id', DB::table('workspaces')->where(
                fn ($q) => $q->whereNull('is_gencys_partner')->orWhere('is_gencys_partner', false)
            )->select('id'))
            ->where('cod_fee_rate', PancakeOrderSource::COD_FEE_RATE)
            ->update(['cod_fee_rate' => IncomeStatementSetting::DEFAULT_COD_FEE_RATE]);
    }
};

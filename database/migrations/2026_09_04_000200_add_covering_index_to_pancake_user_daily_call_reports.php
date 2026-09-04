<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A covering index for the CSR Analytics call cards.
     *
     * Every call figure on the analytics page reads this rollup through
     * CSRController::callReport() — `workspace_id` + a `date` range, optionally
     * narrowed to the viewer's shops — and then sums the RMO columns.
     *
     * Neither existing index serves that. The unique key leads
     * (workspace_id, pancake_user_id, shop_id, date), so `date` sits fourth and
     * cannot be used for the range; MySQL was falling back to it as a plain
     * workspace_id ref and reading every row the workspace has ever rolled up.
     * pucall_workspace_date_index has the right prefix but carries none of the
     * summed columns, so each matching row still costs a lookup in the table.
     *
     * Leading with (workspace_id, date) and then carrying shop_id and the summed
     * columns makes the aggregate index-only — EXPLAIN reports `Using index`
     * rather than `Using index condition`, and the query stops touching the
     * table at all.
     */
    public function up(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            if (in_array('pucall_ws_date_covering', $this->existingIndexes('pancake_user_daily_call_reports'))) {
                return;
            }

            $table->index([
                'workspace_id',
                'date',
                // The team filter narrows on this, so it belongs inside the
                // index rather than as a lookup after it.
                'shop_id',
                // The payload: everything callReport()'s callers sum or max.
                'total_rmo_called',
                'total_rmo_call_time',
                'total_rmo_connected_called',
                'total_rmo_real_called',
                'longest_rmo_call_time',
                'total_rmo_assigned_count',
                'total_rmo_confirmed_count',
            ], 'pucall_ws_date_covering');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            // Guarded rather than dropIndexIfExists(), which this version of the
            // Blueprint does not have.
            if (in_array('pucall_ws_date_covering', $this->existingIndexes('pancake_user_daily_call_reports'))) {
                $table->dropIndex('pucall_ws_date_covering');
            }
        });
    }

    private function existingIndexes(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique()
            ->all();
    }
};

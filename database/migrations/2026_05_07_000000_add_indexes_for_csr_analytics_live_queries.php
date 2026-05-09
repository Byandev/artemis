<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CSR analytics now reads RMO Called and Total Call Time directly from
        // pancake_order_for_delivery (joined to call_logs via EXISTS), instead of
        // the pre-aggregated pancake_user_rmo_daily_reports rollup. The two
        // composite indexes below let those live queries run as range scans
        // instead of full table scans on workspaces with large delivery volume.

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            if (! in_array('idx_pofd_workspace_delivery_date_assignee', $this->existingIndexes('pancake_order_for_delivery'))) {
                $table->index(['workspace_id', 'delivery_date', 'assignee_id'], 'idx_pofd_workspace_delivery_date_assignee');
            }
        });

        Schema::table('call_logs', function (Blueprint $table) {
            if (! in_array('idx_call_logs_workspace_user_date_phone', $this->existingIndexes('call_logs'))) {
                $table->index(['workspace_id', 'user_id', 'call_date', 'phone_number'], 'idx_call_logs_workspace_user_date_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_pofd_workspace_delivery_date_assignee');
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_call_logs_workspace_user_date_phone');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_purchased_orders')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM inventory_purchased_orders'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        if (! in_array('ipo_user_issue_id_idx', $indexes, true)) {
            Schema::table('inventory_purchased_orders', function (Blueprint $table) {
                $table->index(['user_id', 'issue_date', 'id'], 'ipo_user_issue_id_idx');
            });
        }

        if (! in_array('ipo_user_status_issue_idx', $indexes, true)) {
            Schema::table('inventory_purchased_orders', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'issue_date'], 'ipo_user_status_issue_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_purchased_orders')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM inventory_purchased_orders'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        if (in_array('ipo_user_issue_id_idx', $indexes, true)) {
            Schema::table('inventory_purchased_orders', function (Blueprint $table) {
                $table->dropIndex('ipo_user_issue_id_idx');
            });
        }

        if (in_array('ipo_user_status_issue_idx', $indexes, true)) {
            Schema::table('inventory_purchased_orders', function (Blueprint $table) {
                $table->dropIndex('ipo_user_status_issue_idx');
            });
        }
    }
};

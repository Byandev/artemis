<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            // Spreadsheet-ledger fields. Free-text approvers/org so entries mirror
            // the physical expense sheet; status tracks the approval workflow.
            if (! Schema::hasColumn('finance_transactions', 'requested_by')) {
                $table->string('requested_by')->nullable()->after('description');
            }
            if (! Schema::hasColumn('finance_transactions', 'approved_by')) {
                $table->string('approved_by')->nullable()->after('requested_by');
            }
            if (! Schema::hasColumn('finance_transactions', 'department')) {
                $table->string('department')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('finance_transactions', 'charge_to')) {
                $table->string('charge_to')->nullable()->after('department');
            }
            if (! Schema::hasColumn('finance_transactions', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('running_balance');
            }
            if (! Schema::hasColumn('finance_transactions', 'status')) {
                // Existing rows are already-posted ledger entries.
                $table->string('status')->default('posted')->after('reference_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            foreach (['requested_by', 'approved_by', 'department', 'charge_to', 'reference_no', 'status'] as $column) {
                if (Schema::hasColumn('finance_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

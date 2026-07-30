<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reshape the fund request fields: a request now carries a transaction type and a
 * department (mirroring a transaction), while `purpose` is dropped. The request
 * date and requester are no longer entered — the controller stamps them from the
 * creation time and the signed-in user. Guarded so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_fund_requests', 'transaction_type_id')) {
                $table->foreignId('transaction_type_id')->nullable()->after('reference_no')
                    ->constrained('finance_transaction_types')->nullOnDelete();
            }

            if (! Schema::hasColumn('finance_fund_requests', 'department_id')) {
                $table->foreignId('department_id')->nullable()
                    ->constrained('departments')->nullOnDelete();
            }

            if (Schema::hasColumn('finance_fund_requests', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_fund_requests', 'purpose')) {
                $table->text('purpose')->nullable();
            }

            if (Schema::hasColumn('finance_fund_requests', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }

            if (Schema::hasColumn('finance_fund_requests', 'transaction_type_id')) {
                $table->dropConstrainedForeignId('transaction_type_id');
            }
        });
    }
};

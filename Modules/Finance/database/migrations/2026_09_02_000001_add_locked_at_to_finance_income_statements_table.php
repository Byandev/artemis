<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closing a statement for good.
     *
     * A month's figures are a snapshot of live data — regenerating re-reads the
     * orders and transactions as they stand today, so a statement that has been
     * reported on, paid against or filed can quietly change under everyone once
     * a late order lands. Locking is the declaration that this month is settled:
     * it blocks regenerate, blocks a save that would overwrite it, and blocks
     * deleting it, until someone deliberately unlocks.
     *
     * A timestamp rather than a boolean, with who set it, because "when was this
     * month closed, and by whom" is the question actually asked of a locked
     * statement. Null = open.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('generated_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};

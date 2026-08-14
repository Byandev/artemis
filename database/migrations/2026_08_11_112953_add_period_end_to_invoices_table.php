<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which billing period a renewal invoice covers.
     *
     * Null on anything raised by hand or on an upgrade — only the scheduled
     * renewal run fills it. The unique index is what stops a workspace being
     * billed twice for the same renewal: MySQL treats NULLs as distinct, so
     * manual invoices are free to pile up while renewals cannot repeat.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_end')->nullable()->after('due_date');
            $table->unique(['workspace_id', 'period_end'], 'invoices_workspace_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_workspace_period_unique');
            $table->dropColumn('period_end');
        });
    }
};

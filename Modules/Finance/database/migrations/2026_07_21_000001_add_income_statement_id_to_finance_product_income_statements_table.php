<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_product_income_statements', function (Blueprint $table) {
            // The parent workspace income statement (same workspace + month) this
            // product breakdown belongs to. Null until that month's workspace
            // statement exists. Explicit short names (long table name).
            $table->unsignedBigInteger('income_statement_id')->nullable()->after('workspace_id');
            $table->foreign('income_statement_id', 'fpis_parent_fk')
                ->references('id')->on('finance_income_statements')->nullOnDelete();
            $table->index('income_statement_id', 'fpis_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_product_income_statements', function (Blueprint $table) {
            $table->dropForeign('fpis_parent_fk');
            $table->dropIndex('fpis_parent_idx');
            $table->dropColumn('income_statement_id');
        });
    }
};

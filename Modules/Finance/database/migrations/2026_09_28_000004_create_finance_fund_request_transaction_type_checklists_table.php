<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which checklist requirements a fund request of each transaction type is
     * checked against. One requirement can be on any number of types' checklists.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_transaction_type_checklists', function (Blueprint $table) {
            $table->foreignId('transaction_type_id')->constrained('finance_transaction_types', indexName: 'fund_req_type_checklist_type_foreign')->cascadeOnDelete();
            $table->foreignId('checklist_requirement_id')->constrained('finance_fund_request_checklist_requirements', indexName: 'fund_req_type_checklist_req_foreign')->cascadeOnDelete();

            $table->primary(['transaction_type_id', 'checklist_requirement_id'], 'fund_request_type_checklist_primary');
            $table->index('checklist_requirement_id', 'fund_req_type_checklist_req_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_transaction_type_checklists');
    }
};

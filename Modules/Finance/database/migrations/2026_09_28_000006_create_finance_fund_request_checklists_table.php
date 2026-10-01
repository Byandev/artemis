<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The checklist requirements ticked off on a fund request, out of the
     * checklist its transaction type carries.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_fund_requests', indexName: 'fund_req_checklist_request_foreign')->cascadeOnDelete();
            $table->foreignId('checklist_requirement_id')->constrained('finance_fund_request_checklist_requirements', indexName: 'fund_req_checklist_req_foreign')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fund_request_id', 'checklist_requirement_id'], 'fund_request_checklist_unique');
            $table->index('checklist_requirement_id', 'fund_req_checklist_req_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_checklists');
    }
};

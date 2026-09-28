<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The checklist items ticked off on a fund request, out of the checklist its
     * transaction type carries.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_checklist_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_fund_requests')->cascadeOnDelete();
            $table->foreignId('checklist_id')->constrained('finance_fund_request_checklists')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fund_request_id', 'checklist_id'], 'fund_request_checklist_check_unique');
            $table->index('checklist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_checklist_checks');
    }
};

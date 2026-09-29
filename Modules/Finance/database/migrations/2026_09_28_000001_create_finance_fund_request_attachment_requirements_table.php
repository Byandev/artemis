<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The attachments a fund request can call for. The workspace keeps one list, managed on the finance
     * management page; which transaction types call for each is set in
     * finance_fund_request_transaction_type_attachments.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_attachment_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained(indexName: 'fund_req_attachment_req_workspace_foreign')->cascadeOnDelete();
            $table->string('name');

            $table->unique(['workspace_id', 'name'], 'fund_req_attachment_req_workspace_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_attachment_requirements');
    }
};

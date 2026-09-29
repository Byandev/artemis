<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which attachment requirements a fund request of each transaction type calls
     * for. One requirement can be called for by any number of types.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_transaction_type_attachments', function (Blueprint $table) {
            $table->foreignId('transaction_type_id')->constrained('finance_transaction_types', indexName: 'fund_req_type_attachment_type_foreign')->cascadeOnDelete();
            $table->foreignId('attachment_requirement_id')->constrained('finance_fund_request_attachment_requirements', indexName: 'fund_req_type_attachment_req_foreign')->cascadeOnDelete();

            $table->primary(['transaction_type_id', 'attachment_requirement_id'], 'fund_request_type_attachment_primary');
            $table->index('attachment_requirement_id', 'fund_req_type_attachment_req_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_transaction_type_attachments');
    }
};

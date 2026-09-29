<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The attachments a fund request has on file: one row per attachment
     * requirement it answers, the uploaded file held by media-library.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_fund_requests', indexName: 'fund_req_attachment_request_foreign')->cascadeOnDelete();
            $table->foreignId('attachment_requirement_id')->constrained('finance_fund_request_attachment_requirements', indexName: 'fund_req_attachment_req_foreign')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fund_request_id', 'attachment_requirement_id'], 'fund_request_attachment_unique');
            $table->index('attachment_requirement_id', 'fund_req_attachment_req_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_attachments');
    }
};

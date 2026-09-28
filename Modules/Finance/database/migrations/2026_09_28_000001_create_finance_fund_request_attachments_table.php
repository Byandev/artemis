<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The attachments a fund request calls for. Each transaction type has its own
     * set, created on the finance management page.
     */
    public function up(): void
    {
        Schema::create('finance_fund_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_type_id')->constrained('finance_transaction_types')->cascadeOnDelete();
            $table->string('name');

            $table->unique(['transaction_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fund_request_attachments');
    }
};

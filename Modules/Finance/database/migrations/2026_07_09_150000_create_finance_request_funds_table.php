<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('request_date');
            $table->string('reference_no');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('charge_to')->constrained('users')->cascadeOnDelete();
            $table->text('purpose');
            $table->decimal('amount_requested', 15, 2);
            $table->date('date_needed')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'reference_no']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_funds');
    }
};

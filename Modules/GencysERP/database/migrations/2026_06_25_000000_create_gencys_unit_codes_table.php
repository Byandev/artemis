<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_unit_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Gencys' own unit code id (sent as "row_id" in the n8n payload). The
            // upsert key per workspace; null for manually-created rows.
            $table->unsignedBigInteger('row_id')->nullable();

            $table->string('sku')->nullable();
            $table->string('unit_code')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'row_id']);
            $table->index(['workspace_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_unit_codes');
    }
};

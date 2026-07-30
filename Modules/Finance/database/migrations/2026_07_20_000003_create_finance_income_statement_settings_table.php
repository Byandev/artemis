<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_income_statement_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Per-workspace default rates for the income statement (fractions).
            $table->decimal('cod_fee_rate', 6, 4)->default(0.02); // 2% of delivered
            $table->decimal('vat_rate', 6, 4)->default(0.12);     // 12% of COD fee

            $table->timestamps();

            $table->unique('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_statement_settings');
    }
};

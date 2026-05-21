<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_daily_budget_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('budget', 18, 2)->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'page_id', 'date'], 'pdbr_unique');
            $table->index(['workspace_id', 'date'], 'pdbr_workspace_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_daily_budget_records');
    }
};

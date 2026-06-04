<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name');
            $table->enum('target_type', ['campaign', 'ad_set']);
            $table->enum('condition_operator', ['and', 'or'])->default('and');
            $table->enum('action', ['pause', 'enable', 'increase_budget', 'decrease_budget']);

            // Budget adjustment — only relevant when action is increase/decrease_budget
            $table->enum('adjustment_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('adjustment_value', 20, 4)->nullable();
            $table->decimal('budget_min', 20, 4)->nullable();
            $table->decimal('budget_max', 20, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_rules');
    }
};

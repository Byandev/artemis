<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('optimization_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('target', ['campaign', 'ad_set']); // campaign or ad set
            $table->enum('action', [
                'increase_budget_fixed',
                'decrease_budget_fixed',
                'increase_budget_percentage',
                'decrease_budget_percentage'
            ]);
            $table->decimal('action_value', 10, 2)->nullable(); // Fixed amount or percentage value
            $table->json('conditions'); // Array of conditions (metric, operator, value)
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimization_rules');
    }
};

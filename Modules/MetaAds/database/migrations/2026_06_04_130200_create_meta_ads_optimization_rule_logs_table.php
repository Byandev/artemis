<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');
            $table->unsignedBigInteger('workspace_id');
            $table->enum('target_type', ['campaign', 'ad_set']);
            $table->unsignedBigInteger('target_id');
            $table->string('target_name')->nullable();
            $table->enum('action_taken', ['pause', 'enable', 'increase_budget', 'decrease_budget']);

            // Populated for budget change actions
            $table->decimal('previous_value', 20, 4)->nullable();
            $table->decimal('new_value', 20, 4)->nullable();

            // Snapshot of every condition at evaluation time so you can verify why the rule fired.
            // Each entry: { metric, operator, threshold, actual_value, time_window, passed }
            $table->json('conditions_snapshot');

            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamps();

            $table->foreign('meta_ads_optimization_rule_id')
                ->references('id')
                ->on('meta_ads_optimization_rules')
                ->onDelete('cascade');

            $table->index(['workspace_id', 'triggered_at']);
            $table->index(['meta_ads_optimization_rule_id', 'triggered_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_rule_logs');
    }
};

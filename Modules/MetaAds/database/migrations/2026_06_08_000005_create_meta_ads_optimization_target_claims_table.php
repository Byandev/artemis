<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (run, campaign/ad set). The unique key is what guarantees a
     * single entity is only ever changed by one rule within a run: the first
     * rule's apply job to insert here wins; any other rule's job for the same
     * target that run sees the row already taken and becomes a no-op.
     */
    public function up(): void
    {
        Schema::create('meta_ads_optimization_target_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');
            $table->string('target_type');
            $table->string('target_id');
            $table->timestamp('claimed_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'target_type', 'target_id'], 'opt_target_claim_unique');
            $table->index(['workspace_id', 'meta_ads_optimization_rule_id'], 'opt_target_claim_ws_rule_index');

            $table->foreign('workspace_id', 'opt_target_claim_ws_fk')
                ->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_ads_optimization_rule_id', 'opt_target_claim_rule_fk')
                ->references('id')->on('meta_ads_optimization_rules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_target_claims');
    }
};

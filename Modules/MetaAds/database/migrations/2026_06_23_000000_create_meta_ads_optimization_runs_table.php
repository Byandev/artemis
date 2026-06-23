<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            // Each run tracks one rule end-to-end (sync its accounts, then
            // evaluate just that rule). Nullable so a run can outlive its rule.
            $table->unsignedBigInteger('meta_ads_optimization_rule_id')->nullable();
            $table->string('status', 16)->default('running');
            // The phase currently executing (null once the run finishes ok).
            $table->string('current_step', 32)->nullable();
            // Ordered plan of phases for this run: [{key, label}, ...].
            $table->json('steps')->nullable();
            $table->unsignedInteger('total_rules')->default(0);
            $table->unsignedInteger('total_accounts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            // Explicit short name — the auto-generated one exceeds MySQL's 64-char limit.
            $table->index(['meta_ads_optimization_rule_id', 'id'], 'opt_runs_rule_idx');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_runs');
    }
};

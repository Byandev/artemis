<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');
            $table->unsignedBigInteger('meta_ads_account_id');

            $table->enum('target_type', ['campaign', 'ad_set']);
            $table->unsignedBigInteger('target_id');
            $table->string('target_name')->nullable();
            $table->enum('action', ['pause', 'enable', 'increase_budget', 'decrease_budget']);

            // Budget actions only.
            $table->decimal('current_value', 20, 4)->nullable();
            $table->decimal('new_value', 20, 4)->nullable();

            // Snapshot of each condition at evaluation time (metric/threshold/actual/passed).
            $table->json('conditions_snapshot');

            $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Explicit short names — auto-generated identifiers exceed MySQL's limit.
            $table->foreign('workspace_id', 'mop_workspace_id_foreign')
                ->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('meta_ads_optimization_rule_id', 'mop_rule_id_foreign')
                ->references('id')->on('meta_ads_optimization_rules')->onDelete('cascade');
            $table->foreign('meta_ads_account_id', 'mop_account_id_foreign')
                ->references('id')->on('meta_ads_accounts')->onDelete('cascade');
            $table->foreign('reviewed_by', 'mop_reviewed_by_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['workspace_id', 'status'], 'mop_workspace_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_proposals');
    }
};

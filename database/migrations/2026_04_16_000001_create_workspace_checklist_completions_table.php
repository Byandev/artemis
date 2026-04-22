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
        Schema::create('workspace_checklist_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_checklist_id')->constrained('workspace_checklists')->cascadeOnDelete();
            $table->morphs('target');
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'workspace_checklist_id', 'target_type', 'target_id'],
                'workspace_checklist_completions_unique_target'
            );
            $table->index(
                ['workspace_id', 'target_type', 'target_id'],
                'workspace_checklist_completions_workspace_target_idx'
            );
            $table->index(
                ['workspace_id', 'workspace_checklist_id'],
                'workspace_checklist_completions_workspace_checklist_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_checklist_completions');
    }
};

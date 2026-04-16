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
        Schema::create('workspace_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->enum('target', ['Shop', 'Page']);
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'target'], 'workspace_checklists_workspace_target_idx');
            $table->index(['workspace_id', 'required'], 'workspace_checklists_workspace_required_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_checklists');
    }
};

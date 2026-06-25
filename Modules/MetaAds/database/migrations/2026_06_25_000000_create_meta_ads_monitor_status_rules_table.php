<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_monitor_status_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            // The three fixed statuses from the creative-testing sheet.
            $table->enum('status', ['scaling', 'maintain', 'killed']);
            $table->enum('condition_operator', ['and', 'or'])->default('and');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('workspace_id');
            // One rule per status per workspace.
            $table->unique(['workspace_id', 'status'], 'maomsr_workspace_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_monitor_status_rules');
    }
};

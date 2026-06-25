<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_monitor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->enum('level', ['campaign', 'ad_set', 'ad']);
            $table->unsignedBigInteger('entity_id');
            $table->date('date');

            // The window aggregates used for this evaluation (day spend/roas, 3d, 7d…).
            $table->json('metrics')->nullable();
            // 'scaling'|'maintain'|'killed'|'too_early'|'unmatched' — string, not
            // enum, so the non-status outcomes can be stored too.
            $table->string('suggested_status', 16)->nullable();
            // Per-condition snapshot explaining WHY the status was assigned.
            $table->json('reason')->nullable();
            $table->timestamp('evaluated_at')->nullable();

            $table->timestamps();

            // One evaluation per entity per day. Short names — MySQL 64-char limit.
            $table->unique(['workspace_id', 'level', 'entity_id', 'date'], 'maome_entity_date_unique');
            $table->index(['workspace_id', 'level', 'date'], 'maome_ws_level_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_monitor_evaluations');
    }
};

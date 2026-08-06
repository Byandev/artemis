<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One team's amount within a dated sales target. The date lives on the parent,
 * so a row here is only ever "team X should hit Y on that target's date".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_target_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Same precision as team_ad_spend_goals.daily_target, so the money
            // columns round-trip identically.
            $table->decimal('sales_target', 15, 2);

            $table->timestamps();

            // A team appears at most once per dated target — a second amount for
            // the same team would make "the team's target that day" ambiguous.
            $table->unique(['sales_target_id', 'team_id'], 'stt_target_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_target_teams');
    }
};

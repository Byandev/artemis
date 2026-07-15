<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ad_spend_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // The per-day ad-spend amount the team should hit (e.g. 500000.00).
            // A goal is judged daily, not accumulated over the window.
            $table->decimal('daily_target', 15, 2);

            // The window the daily goal applies to (and over which the peak day
            // is measured).
            $table->date('start_date');
            $table->date('end_date');

            $table->timestamps();

            $table->index(['workspace_id', 'team_id'], 'tasg_ws_team_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ad_spend_goals');
    }
};

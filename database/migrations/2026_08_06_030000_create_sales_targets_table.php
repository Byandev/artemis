<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dated set of per-team sales targets — one header row per date, with the
 * amounts hanging off it in sales_target_teams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // The day this set of targets applies to. The team rows inherit it
            // rather than storing their own copy, so the two can't disagree.
            $table->date('date');

            // Free-text label for the set, e.g. "7:7".
            $table->string('name');

            $table->timestamps();

            // The list is always read as "this workspace's targets, newest first".
            $table->index(['workspace_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};

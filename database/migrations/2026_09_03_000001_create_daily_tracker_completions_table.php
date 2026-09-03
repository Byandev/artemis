<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_tracker_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_tracker_item_id')->constrained()->cascadeOnDelete();
            // Whose row was ticked — not necessarily who ticked it. Rows survive
            // a role change: taking someone off the board hides their column
            // from that day on, it does not erase the days they worked.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The first day of the period the tick satisfies (the day itself for
            // a daily item, the Monday of the week for a weekly one), so the
            // board can look a period up by equality rather than by range.
            $table->date('tracked_on');
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(
                ['daily_tracker_item_id', 'user_id', 'tracked_on'],
                'daily_tracker_completions_unique_period'
            );
            // Covers the board's one read: every completion in a workspace for
            // the periods on screen.
            $table->index(
                ['workspace_id', 'tracked_on'],
                'daily_tracker_completions_workspace_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tracker_completions');
    }
};

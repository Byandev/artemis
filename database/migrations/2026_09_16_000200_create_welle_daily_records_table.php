<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welle_daily_records', function (Blueprint $table) {
            $table->id();

            // Scoped to a workspace like every other domain record, but keyed
            // on the user too: Welle credentials are personal, so a user in two
            // Welle-enabled workspaces gets the same day written to both.
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('date');

            // The three ESC (Extreme Self Care) pillars. Welle sends a row for
            // every elapsed day, including ones where nothing was ticked, which
            // is what makes "6 of 15 days" countable from this table alone.
            $table->boolean('movement')->default(false);
            $table->boolean('meditation')->default(false);
            $table->boolean('learning')->default(false);

            // Denormalised so the calendar's "3 of 3 / 2 of 3 / 1 or none"
            // banding is an indexable column rather than three ORs, and the
            // ESC definition (all three done) lives in one place.
            $table->unsignedTinyInteger('pillars_completed')->default(0);
            $table->boolean('is_esc')->default(false);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // One row per user per day — re-fetching a day corrects it.
            $table->unique(['workspace_id', 'user_id', 'date'], 'welle_daily_ws_user_date_unique');

            // "Everyone's day" (leaderboards, workspace rollups) and "this
            // user's completed days" (streaks, the month calendar).
            $table->index(['workspace_id', 'date'], 'welle_daily_ws_date_index');
            $table->index(['user_id', 'is_esc', 'date'], 'welle_daily_user_esc_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welle_daily_records');
    }
};

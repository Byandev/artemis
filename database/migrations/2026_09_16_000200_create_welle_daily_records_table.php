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

            // The three ESC (Extreme Self Care) pillars. A day with none of
            // them ticked is not written at all, so a row here means something
            // was done that day and `pillars_completed` is never 0.
            $table->boolean('movement')->default(false);
            $table->boolean('meditation')->default(false);
            $table->boolean('learning')->default(false);

            // Derived from the three above and stored anyway. Every read of
            // this table is an aggregate over a month of days for one user, or
            // over a workspace of users, and there are a great many of both —
            // so the count and the ESC verdict are worth paying for once at
            // write time rather than recomputing across three columns on every
            // card, every bar and every rollup. upsertDaily is the only writer,
            // which is what keeps them honest.
            $table->unsignedTinyInteger('pillars_completed')->default(0);
            $table->boolean('is_esc')->default(false);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // One row per user per day — re-fetching a day corrects it.
            $table->unique(['workspace_id', 'user_id', 'date'], 'welle_daily_ws_user_date_unique');

            // "Everyone's day" (leaderboards, workspace rollups) and "this
            // user's completed days" (the ESC rate, the month calendar), the
            // latter answered from the index alone rather than from the rows.
            $table->index(['workspace_id', 'date'], 'welle_daily_ws_date_index');
            $table->index(['user_id', 'is_esc', 'date'], 'welle_daily_user_esc_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welle_daily_records');
    }
};

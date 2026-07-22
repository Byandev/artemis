<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-employee ESC reminder settings. One row per user, so the daily
     * reminder can be enabled, timed, and localised individually.
     *
     * `reminder_time` is a wall-clock time interpreted in `reminder_timezone`
     * (not UTC) — 20:00 means 8pm where the employee actually is.
     * `last_reminded_at` is stamped after a reminder goes out so the same day
     * can't be reminded twice.
     */
    public function up(): void
    {
        Schema::create('esc_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('reminder_enabled')->default(true);
            $table->time('reminder_time')->default('20:00:00');
            $table->string('reminder_timezone', 64)->default('Asia/Manila');
            $table->string('reminder_style', 16)->default('gentle');
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            // Settings are per user, so exactly one row each.
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esc_notifications');
    }
};

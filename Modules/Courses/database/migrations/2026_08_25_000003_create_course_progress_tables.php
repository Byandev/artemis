<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Progress is per user per course. Enrollment records that someone started
     * a course and where they left off; completions record the individual
     * lessons they finished.
     *
     * Kept in two tables rather than a counter on the enrollment so a lesson
     * added to a course later doesn't silently change anyone's percentage into
     * something that no longer matches which lessons they actually watched.
     */
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            // Where to drop the learner back in when they continue.
            $table->foreignId('last_lesson_id')->nullable()
                ->constrained('course_lessons')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
        });

        Schema::create('course_lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['course_lesson_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_completions');
        Schema::dropIfExists('course_enrollments');
    }
};

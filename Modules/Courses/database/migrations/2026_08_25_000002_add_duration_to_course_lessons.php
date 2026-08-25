<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lesson length, so the outline can show a duration per lesson and the
     * course header a total, without every video having to be loaded first.
     *
     * Read from the file by the browser at upload time rather than probed
     * server-side: uploads go straight to S3, so the app never holds the bytes
     * and has nothing to run ffprobe against.
     */
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};

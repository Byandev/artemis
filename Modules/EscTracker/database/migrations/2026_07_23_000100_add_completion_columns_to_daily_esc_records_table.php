<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completion flags for the learning and movement pillars, mirroring the
     * `meditation_completed` boolean that already exists. Written by the API
     * when a record is saved.
     */
    public function up(): void
    {
        Schema::table('daily_esc_records', function (Blueprint $table) {
            $table->boolean('learning_completed')->default(false)->after('learning_text');
            $table->boolean('movement_completed')->default(false)->after('movement_image_url');
        });

        // Existing rows predate the columns and would all read as incomplete.
        DB::statement("
            update daily_esc_records
               set learning_completed = (learning_text is not null and learning_text <> ''),
                   movement_completed = (
                       (movement_text is not null and movement_text <> '')
                       or movement_image_url is not null
                   )
        ");
    }

    public function down(): void
    {
        Schema::table('daily_esc_records', function (Blueprint $table) {
            $table->dropColumn(['learning_completed', 'movement_completed']);
        });
    }
};

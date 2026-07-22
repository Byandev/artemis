<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `meditation_url` was added to the create-table migration after it had
     * already run, so existing databases never got the column. Add it here so
     * the schema matches without a destructive refresh.
     */
    public function up(): void
    {
        // The create-table migration was later amended to include this column,
        // so on a fresh migrate it already exists. Guard so this only fills the
        // gap on databases that ran the original create migration.
        if (Schema::hasColumn('daily_esc_records', 'meditation_url')) {
            return;
        }

        Schema::table('daily_esc_records', function (Blueprint $table) {
            $table->string('meditation_url')->nullable()->after('meditation_completed');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('daily_esc_records', 'meditation_url')) {
            return;
        }

        Schema::table('daily_esc_records', function (Blueprint $table) {
            $table->dropColumn('meditation_url');
        });
    }
};

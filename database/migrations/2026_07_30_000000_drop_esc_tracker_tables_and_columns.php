<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop everything the removed EscTracker module owned.
     *
     * The module's own migrations went away with `Modules/EscTracker/`, so this
     * root migration cleans up databases where they had already run. Guards on
     * every drop keep it a no-op on databases that never had the module.
     *
     * Irreversible: `down()` recreates the schema but not the data.
     */
    public function up(): void
    {
        Schema::dropIfExists('esc_notifications');
        Schema::dropIfExists('daily_esc_records');

        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'esc_tracker_module_enabled')) {
                $table->dropColumn('esc_tracker_module_enabled');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['reminder_time', 'current_streak', 'longest_streak'],
                fn (string $column) => Schema::hasColumn('users', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('reminder_time')->nullable()->after('is_super_admin');
            $table->unsignedInteger('current_streak')->default(0)->after('reminder_time');
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('esc_tracker_module_enabled')->default(false);
        });
    }
};

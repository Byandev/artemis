<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-workspace toggle for the EscTracker module.
     *
     * Lives on the core `workspaces` table rather than in the module, matching
     * every other module flag (creatives, meta_ads, gencys, …) — the admin
     * "Toggle Modules" screen reads them all from one row.
     *
     * Defaults to off so the module is opt-in per workspace.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'esc_tracker_module_enabled')) {
                $table->boolean('esc_tracker_module_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'esc_tracker_module_enabled')) {
                $table->dropColumn('esc_tracker_module_enabled');
            }
        });
    }
};

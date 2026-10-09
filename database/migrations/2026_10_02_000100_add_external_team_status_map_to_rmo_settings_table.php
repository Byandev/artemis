<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which CX / rider status each external-team sheet status tags, picked in
     * Settings → RMO settings. Null until first saved; the sync falls back to
     * the default statuses by name until then.
     */
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->json('external_team_status_map')->nullable()->after('external_team_sheet_url');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn('external_team_status_map');
        });
    }
};

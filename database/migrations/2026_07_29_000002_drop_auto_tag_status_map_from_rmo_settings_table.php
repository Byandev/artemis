<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-tagging is a plain on/off switch — the parcel status => RMO status
     * pairs are fixed (delivered, returning), so there is nothing per-workspace
     * left to store.
     */
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn('auto_tag_status_map');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->json('auto_tag_status_map')->nullable()->after('enable_auto_tag_status');
        });
    }
};

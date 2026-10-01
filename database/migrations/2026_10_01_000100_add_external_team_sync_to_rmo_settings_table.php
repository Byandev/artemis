<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->boolean('enable_external_team_sync')->default(false)->after('enable_auto_tag_status');
            $table->string('external_team_sheet_url', 512)->nullable()->after('enable_external_team_sync');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn(['enable_external_team_sync', 'external_team_sheet_url']);
        });
    }
};

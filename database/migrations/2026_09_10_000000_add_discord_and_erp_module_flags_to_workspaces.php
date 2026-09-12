<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('discord_notifications_module_enabled')->default(false)->after('courses_module_enabled');
            $table->boolean('erp_integration_module_enabled')->default(false)->after('discord_notifications_module_enabled');
        });

        // Both settings pages shipped ungated, so every existing workspace can
        // already reach them. Backfill them as enabled rather than silently
        // pulling the pages out from under workspaces already using them; only
        // workspaces created from here on start opted out.
        DB::table('workspaces')->update([
            'discord_notifications_module_enabled' => true,
            'erp_integration_module_enabled' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'discord_notifications_module_enabled',
                'erp_integration_module_enabled',
            ]);
        });
    }
};

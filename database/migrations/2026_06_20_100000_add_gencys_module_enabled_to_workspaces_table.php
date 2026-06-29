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
            if (! Schema::hasColumn('workspaces', 'gencys_module_enabled')) {
                $table->boolean('gencys_module_enabled')->default(false)->after('meta_ads_module_enabled');
            }
        });

        // Turn it on for existing workspaces that already have ERP credentials
        // configured, so the Gencys ERP module surfaces for current users.
        DB::table('workspaces')
            ->whereNotNull('erp_username')
            ->update(['gencys_module_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'gencys_module_enabled')) {
                $table->dropColumn('gencys_module_enabled');
            }
        });
    }
};

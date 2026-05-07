<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->renameColumn('show_inventory', 'inventory_module_enabled');
            $table->renameColumn('show_finance', 'finance_module_enabled');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('products_module_enabled')->default(false)->after('finance_module_enabled');
            $table->boolean('teams_module_enabled')->default(false)->after('products_module_enabled');
            $table->boolean('checklist_module_enabled')->default(false)->after('teams_module_enabled');
            $table->boolean('csr_module_enabled')->default(false)->after('checklist_module_enabled');
            $table->boolean('rmo_module_enabled')->default(false)->after('csr_module_enabled');
            $table->boolean('leaderboard_module_enabled')->default(false)->after('rmo_module_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'products_module_enabled',
                'teams_module_enabled',
                'checklist_module_enabled',
                'csr_module_enabled',
                'rmo_module_enabled',
                'leaderboard_module_enabled',
            ]);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->renameColumn('inventory_module_enabled', 'show_inventory');
            $table->renameColumn('finance_module_enabled', 'show_finance');
        });
    }
};

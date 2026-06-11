<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('workspaces', 'public_password')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            // Hashed password gating the public RMO management & leaderboard pages.
            $table->string('public_password')->nullable()->after('rmo_module_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'public_password')) {
                $table->dropColumn('public_password');
            }
        });
    }
};

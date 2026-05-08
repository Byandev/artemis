<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('botcake_module_enabled')->default(false)->after('leaderboard_module_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('botcake_module_enabled');
        });
    }
};

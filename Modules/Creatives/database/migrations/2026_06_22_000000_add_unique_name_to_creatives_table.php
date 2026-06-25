<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            // Creative names must be unique within a workspace (but the same name
            // may exist across different workspaces).
            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'name']);
        });
    }
};

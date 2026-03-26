<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->index(
                ['workspace_id', 'status', 'shipped_at', 'confirmed_at'],
                'idx_workspace_status_shipped_confirmed_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_status_shipped_confirmed_idx');
        });
    }
};

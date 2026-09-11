<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day an order came in, indexed the way the day it was confirmed is.
     *
     * The verification match — App\Support\CallLogPersona::resolveVerification,
     * on the call sync path — now reads a day's orders on inserted_at as well
     * as confirmed_at. confirmed_at has had idx_orders_workspace_confirmed_status
     * in front of it since the start; inserted_at had nothing, so that half of
     * the match was a scan of every order the workspace has ever taken.
     */
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->index(['workspace_id', 'inserted_at'], 'idx_orders_workspace_inserted');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_workspace_inserted');
        });
    }
};

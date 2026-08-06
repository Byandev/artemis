<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A date holds at most one sales target per workspace.
 *
 * Without this, two targets could exist for the same day and "the target for
 * that date" would be ambiguous the moment anything starts reading it. The
 * controller validates the same rule for a readable error; this is the backstop
 * that also covers writes that don't go through it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            // Add before dropping: the workspace_id foreign key is being served
            // by the old composite index, and MySQL refuses to drop an index a
            // constraint still needs. The new unique index leads with the same
            // column, so it takes over that job and frees the old one.
            $table->unique(['workspace_id', 'date'], 'sales_targets_ws_date_unique');
            $table->dropIndex('sales_targets_workspace_id_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->index(['workspace_id', 'date'], 'sales_targets_workspace_id_date_index');
            $table->dropUnique('sales_targets_ws_date_unique');
        });
    }
};

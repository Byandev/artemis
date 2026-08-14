<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            // Nullable: runs opened by the older per-type commands (and every run
            // that predates batching) have no batch, and nullOnDelete keeps those
            // runs readable as history if a batch row is ever pruned.
            $table->foreignId('batch_id')
                ->nullable()
                ->after('workspace_id')
                ->constrained('gencys_sync_batches')
                ->nullOnDelete();

            // Counting a batch's outstanding runs is the guard's inner query.
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            // Order matters: MySQL refuses to drop an index the foreign key
            // still depends on, so the constraint has to go first.
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id', 'status']);
            $table->dropColumn('batch_id');
        });
    }
};

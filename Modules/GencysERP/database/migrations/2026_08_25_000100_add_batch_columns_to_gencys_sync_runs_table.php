<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            // Null for the legacy fire-and-forget runs and anything dispatched
            // outside a batch; the batch keeps its audit trail if it's deleted.
            $table->foreignId('gencys_sync_batch_id')->nullable()->after('workspace_id')
                ->constrained('gencys_sync_batches')->nullOnDelete();

            // Runs sharing a group key can travel in one n8n request — same
            // workspace, same date/range, same flow.
            $table->string('group_key', 191)->nullable()->after('sync_type');

            // 0 on the first send; incremented each time the run is re-queued
            // after a timeout or a failed handshake.
            $table->unsignedTinyInteger('attempt')->default(0)->after('rows_saved');

            // started_at is when the run was created (queued); these two are when
            // it actually went out to n8n and when its callback stops being awaited.
            $table->timestamp('sent_at')->nullable()->after('started_at');
            $table->timestamp('timeout_at')->nullable()->after('sent_at');

            $table->index(['gencys_sync_batch_id', 'status']);
            $table->index(['status', 'timeout_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            $table->dropForeign(['gencys_sync_batch_id']);
            $table->dropIndex(['gencys_sync_batch_id', 'status']);
            $table->dropIndex(['status', 'timeout_at']);
            $table->dropColumn(['gencys_sync_batch_id', 'group_key', 'attempt', 'sent_at', 'timeout_at']);
        });
    }
};

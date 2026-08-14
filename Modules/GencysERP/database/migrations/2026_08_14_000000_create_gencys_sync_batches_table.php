<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('trigger', 16)->default('schedule');  // schedule | manual | retry

            // running   — dispatched, waiting on n8n callbacks
            // completed — every run resolved, none failed
            // partial   — every run resolved, some failed
            // failed    — every run failed (or the outbound handshake died)
            // skipped   — the guard refused to dispatch: previous batch still running
            $table->string('status', 16)->default('running');

            // The sync types this batch covers, so the retry path knows what to replay.
            $table->json('sync_types')->nullable();

            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('completed_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);

            $table->text('message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // The overlap guard's hot path: "is anything still running for this workspace?"
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_sync_batches');
    }
};

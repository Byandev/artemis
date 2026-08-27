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

            // Null = the batch spans every ERP-connected workspace (how the cron
            // creates them). Set = the batch was raised from one workspace's UI
            // and only touches that workspace's records.
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // The sync types this batch covers, in the order it works through
            // them. A scheduled pass carries all of them; a hand-raised batch
            // usually carries one. Each run records its own type, so a group
            // never mixes them.
            $table->json('sync_types');

            // queued | running | completed | completed_with_failures | cancelled
            $table->string('status', 24)->default('queued');
            $table->string('source', 16)->default('cron');   // cron | manual

            // The options this batch was raised with, keyed by sync type:
            // { "transaction_history": { "dates": [...] }, "purchase_order": {...} }.
            // Rebuilt into the n8n payload at send time so a batch queued this
            // morning still uses current ERP credentials.
            $table->json('parameters')->nullable();
            // Stable hash of the type list + parameters, so a repeat cron tick can
            // collapse into an identical batch that hasn't started yet.
            $table->string('parameters_signature', 64)->nullable();

            // Runs per n8n request. Null lets each sync type use its own size —
            // 20 for the flows whose payload carries an items[] array, 1 for the
            // flows n8n only accepts singly. Set to pin every type to one number.
            $table->unsignedSmallInteger('group_size')->nullable();
            // How long a sent group may wait for its callback before its runs are
            // retried (or failed once the attempts run out).
            $table->unsignedInteger('timeout_seconds')->default(600);
            $table->unsignedTinyInteger('max_retries')->default(2);

            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('succeeded_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);
            $table->unsignedInteger('cancelled_runs')->default(0);

            $table->text('message')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['workspace_id', 'status']);
            $table->index('parameters_signature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_sync_batches');
    }
};

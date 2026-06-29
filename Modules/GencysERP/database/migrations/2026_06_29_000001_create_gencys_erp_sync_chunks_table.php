<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per dispatched job — the chunk of (up to 10) inventory items n8n is
 * asked to fetch in a single ERP session. This is the unit that gets retried:
 * a failed chunk re-dispatches the same items from its stored item_ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_erp_sync_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gencys_erp_sync_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);                 // transaction_history | purchase_order
            $table->string('status', 16)->default('pending'); // pending | sent | confirmed | failed
            $table->json('item_ids');                   // inventory_item ids in this chunk
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('webhook_status')->nullable(); // HTTP status n8n returned
            $table->unsignedInteger('items_synced')->nullable();        // records confirmed via callback
            $table->text('error_message')->nullable();
            $table->timestamp('dispatched_at')->nullable(); // when the job POSTed to n8n
            $table->timestamp('confirmed_at')->nullable();  // when n8n called back
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_erp_sync_chunks');
    }
};

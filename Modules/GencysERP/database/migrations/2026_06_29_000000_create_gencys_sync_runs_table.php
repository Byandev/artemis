<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Nullable + nullOnDelete so the audit trail survives if an item is removed.
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            $table->string('sync_type', 32);                   // transaction_history | purchase_order
            $table->string('status', 16)->default('pending');  // pending | success | failed | skipped
            $table->unsignedInteger('rows_received')->default(0);
            $table->unsignedInteger('rows_saved')->default(0);
            $table->text('message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'sync_type', 'status']);
            $table->index(['inventory_item_id', 'sync_type']);
            $table->index(['workspace_id', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_sync_runs');
    }
};

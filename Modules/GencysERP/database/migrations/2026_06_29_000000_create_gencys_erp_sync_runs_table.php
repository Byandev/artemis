<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per trigger of an ERP fetch (transaction history or purchase orders)
 * for a single workspace. Groups the dispatched job chunks so the monitoring
 * page can roll their status up into "completed / partial / failed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_erp_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);                 // transaction_history | purchase_order
            $table->string('status', 16)->default('running'); // running | completed | partial | failed
            $table->string('trigger', 16)->default('schedule'); // schedule | manual | retry
            $table->unsignedBigInteger('triggered_by')->nullable(); // users.id (null = scheduled)
            $table->json('params')->nullable();         // { date } or { start_date, end_date }
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('items_synced')->default(0); // records confirmed back by n8n
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'type', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_erp_sync_runs');
    }
};

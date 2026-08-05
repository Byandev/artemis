<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per inventory item per day: a frozen copy of everything the items list
 * shows, so picking a past date in the list reproduces that day exactly.
 *
 * Both halves matter. The stored item columns (sku, lead_time, …) are copied because
 * they can be edited at any time, and the computed metrics (current_stocks, po_needed,
 * …) because they are derived from transactions, counts and purchase orders that keep
 * moving — recomputing them later would never reproduce the day being asked about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->date('snapshot_date');

            // ---- Copied straight from inventory_items ----
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_parent')->default(false);
            $table->string('sku');
            $table->boolean('is_active')->default(true);
            $table->text('sales_keywords')->nullable();
            $table->text('transaction_keywords')->nullable();
            $table->unsignedInteger('lead_time')->default(0);
            $table->unsignedInteger('unfulfilled_count')->default(0);
            $table->decimal('three_days_average', 10, 4)->default(0);
            $table->integer('remaining_qty')->nullable();
            // The item's own created_at, so the list's created_at sort still works.
            $table->timestamp('item_created_at')->nullable();

            // ---- Computed metrics, as displayed on the day ----
            // Nullable throughout: the list distinguishes "no data" (—) from zero.
            $table->integer('current_stocks')->nullable();
            $table->integer('discrepancy')->nullable();
            $table->integer('discrepancy_counted_qty')->nullable();
            $table->date('discrepancy_date')->nullable();
            $table->integer('waiting_for_delivery_stocks')->nullable();
            $table->decimal('remaining_after_fulfillment', 14, 4)->nullable();
            $table->decimal('stocks_needed_for_lead_time', 14, 4)->nullable();
            $table->decimal('po_needed', 14, 4)->nullable();
            $table->decimal('days_it_can_last', 14, 4)->nullable();

            // ---- Denormalised product bits the list renders ----
            // Copied so a snapshot row is self-describing even if the product is
            // later renamed, reassigned or deleted.
            $table->string('product_name')->nullable();
            $table->date('product_winning_date')->nullable();

            $table->timestamps();

            // Re-running the command for a date overwrites that date rather than
            // stacking duplicates (see the updateOrInsert in the snapshot command).
            $table->unique(['inventory_item_id', 'snapshot_date'], 'inv_item_snapshot_unique');
            $table->index(['workspace_id', 'snapshot_date']);
            $table->index(['snapshot_date', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_snapshots');
    }
};

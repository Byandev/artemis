<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ERP's audit trail for a purchase order — who moved it to each stage
     * and when. Replaced wholesale on every sync, so nothing here is authored
     * locally and none of it is edited in the app.
     */
    public function up(): void
    {
        Schema::create('inventory_purchased_order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_purchased_order_id');
            $table->foreign('inventory_purchased_order_id', 'ipos_logs_order_id_foreign')
                ->references('id')->on('inventory_purchased_orders')->onDelete('cascade');

            // The ERP's own stage label ("Approve", "To Pay", "Paid", …), kept
            // verbatim rather than mapped onto PurchasedOrder::STATUSES: the two
            // vocabularies don't line up one-to-one and the log is a record of
            // what the ERP said, not our interpretation of it.
            $table->string('status');
            $table->string('by')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            // Reading an order's trail, and finding its Paid entry.
            $table->index(['inventory_purchased_order_id', 'logged_at'], 'ipos_logs_order_logged_index');
            $table->index(['inventory_purchased_order_id', 'status'], 'ipos_logs_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchased_order_status_logs');
    }
};

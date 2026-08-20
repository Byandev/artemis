<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            // Who the order was raised with. The ERP sends a free-text name
            // ("BFM", "Kintara Manuf Ventures Inc"), not an id, so there is no
            // suppliers table to point at yet.
            $table->string('supplier')->nullable()->after('control_no');

            // Denormalised from the order's status log: when it was first
            // marked Paid. Kept as a column rather than derived on read so the
            // PO lists can sort and filter on it without touching the log.
            $table->timestamp('paid_at')->nullable()->after('status');

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn(['supplier', 'paid_at']);
        });
    }
};

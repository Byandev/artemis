<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            // The delivery row the call matched, alongside the Pancake order id
            // already stamped next to it. The two are not interchangeable: an
            // order re-loaded for delivery on a later day gets a second
            // pancake_order_for_delivery row against the same order_id, so only
            // this id says which of them the call belongs to.
            //
            // Nullable and unconstrained for the same reasons as order_id: a
            // call only earns one once it matches a delivery, and deliveries are
            // synced from a third party, so a foreign key would turn a vanished
            // row into a sync error.
            $table->unsignedBigInteger('order_for_delivery_id')->nullable()->after('order_id');

            $table->index('order_for_delivery_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex(['order_for_delivery_id']);
            $table->dropColumn('order_for_delivery_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workspace-defined CX and rider statuses for the RMO page. They sit next to
     * the existing `status` column rather than replacing it, so an order carries
     * all three.
     */
    public function up(): void
    {
        foreach (['pancake_order_for_delivery_cx_statuses', 'pancake_order_for_delivery_rider_statuses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 50);
                $table->timestamps();

                $table->unique(['workspace_id', 'name']);
            });
        }

        // Plain indexed columns, no FK: for-delivery rows are upserted by the
        // Pancake sync, and the controller clears these when a status is deleted.
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('cx_status_id')->nullable()->after('status')->index();
            $table->unsignedBigInteger('rider_status_id')->nullable()->after('cx_status_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropIndex(['cx_status_id']);
            $table->dropIndex(['rider_status_id']);
            $table->dropColumn(['cx_status_id', 'rider_status_id']);
        });

        Schema::dropIfExists('pancake_order_for_delivery_rider_statuses');
        Schema::dropIfExists('pancake_order_for_delivery_cx_statuses');
    }
};

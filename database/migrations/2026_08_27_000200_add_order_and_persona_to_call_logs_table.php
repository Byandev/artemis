<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            // The Pancake order the call was about. Nullable: a call only earns
            // one once it can be matched to an order, and plenty never are.
            // No foreign key — pancake_orders is synced from a third party and
            // rows come and go, which a constraint would turn into sync errors.
            $table->unsignedBigInteger('order_id')->nullable()->after('assignee_user_id');

            // Who was on the other end: 'customer' or 'rider'. Null means the
            // call was not part of an RMO delivery — an order-verification call.
            $table->string('persona', 16)->nullable()->after('order_id');

            // The breakdown groups a workspace's day by persona; this is the
            // index that query runs on.
            $table->index(['workspace_id', 'call_date', 'persona'], 'call_logs_ws_date_persona_idx');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex('call_logs_ws_date_persona_idx');
            $table->dropIndex(['order_id']);
            $table->dropColumn(['order_id', 'persona']);
        });
    }
};

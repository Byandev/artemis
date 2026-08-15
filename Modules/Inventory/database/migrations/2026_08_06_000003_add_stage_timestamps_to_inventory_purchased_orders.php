<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            // When the order reached each workflow stage, sent by the ERP as its
            // own field rather than dug out of the status trail.
            //
            // paid_at already exists (see the supplier/paid_at migration) and
            // keeps its place; these four sit around it in workflow order.
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->timestamp('to_pay_at')->nullable()->after('approved_at');
            // ...paid_at...
            $table->timestamp('for_purchase_at')->nullable()->after('paid_at');
            $table->timestamp('purchased_at')->nullable()->after('for_purchase_at');

            // purchased_at is the release point — the moment the supplier's
            // clock starts — so the flow panels filter and sort on it.
            $table->index('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            $table->dropIndex(['purchased_at']);
            $table->dropColumn([
                'approved_at',
                'to_pay_at',
                'for_purchase_at',
                'purchased_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\PurchasedOrder;

/**
 * Fill in the expected delivery date every order already should have carried.
 *
 * The ERP sends the field on a handful of orders — 5 of 419 on the workspace
 * this was written against — so anything reading it had to invent the same
 * fallback, which is how two readers end up disagreeing about whether an order
 * is late. The standing agreement is two weeks from the issue date, and from
 * here the sync writes it on every order, so this only has to catch up history.
 *
 * Orders with no issue date are left null: there is no day to count from, and a
 * guessed date would read as a commitment nobody made.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_purchased_orders')
            ->whereNull('expected_delivery_date')
            ->whereNotNull('issue_date')
            ->update([
                'expected_delivery_date' => DB::raw(
                    'DATE_ADD(issue_date, INTERVAL '.PurchasedOrder::DEFAULT_DELIVERY_DAYS.' DAY)'
                ),
            ]);
    }

    public function down(): void
    {
        // Deliberately not reversed. The backfilled dates are indistinguishable
        // from the ones the sync now writes, so clearing them would throw away
        // real data alongside the derived kind.
    }
};

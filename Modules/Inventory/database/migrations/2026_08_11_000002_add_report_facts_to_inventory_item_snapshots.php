<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the planning report's figures alongside the item metrics, so a past
 * date reproduces the whole report rather than only the columns the list shows.
 *
 * These are group-level figures written onto every row of the group: demand is
 * counted per group because a customer ordering a variant is demand against the
 * group's supply, and the report has no per-SKU meaning for them. Denormalised
 * rather than given their own table because a snapshot row is meant to be
 * self-describing — reading a past date should never need a second lookup — and
 * because the roll-up reads them with MAX(), which needs no join.
 *
 * Nullable throughout with no backfill. Every one of these is derived from
 * feeds that keep moving — orders, the transaction ledger, purchase orders —
 * so a row written before this column existed cannot be reconstructed, and a 0
 * would read as "there was no demand" rather than "we did not record it".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            // Demand over each window, as totals for the window rather than
            // daily rates: the rate is a division the report can do, and storing
            // the total keeps the row honest about what was counted.
            $table->unsignedInteger('orders_3d')->nullable()->after('three_days_average');
            $table->unsignedInteger('units_3d')->nullable()->after('orders_3d');
            $table->unsignedInteger('orders_7d')->nullable()->after('units_3d');
            $table->unsignedInteger('units_7d')->nullable()->after('orders_7d');
            $table->unsignedInteger('orders_14d')->nullable()->after('units_7d');
            $table->unsignedInteger('units_14d')->nullable()->after('orders_14d');
            // The order feed's own latest day, which those windows were measured
            // back from. Without it a zero is ambiguous between "no demand" and
            // "the feed had not delivered yet".
            $table->date('demand_as_of')->nullable()->after('units_14d');

            $table->date('last_in_date')->nullable()->after('demand_as_of');
            $table->unsignedInteger('last_in_count')->nullable()->after('last_in_date');
            $table->date('last_out_date')->nullable()->after('last_in_count');
            $table->unsignedInteger('last_out_count')->nullable()->after('last_out_date');

            $table->date('last_po_date')->nullable()->after('last_out_count');
            $table->unsignedInteger('last_po_count')->nullable()->after('last_po_date');
            $table->unsignedInteger('raised_not_created_days')->nullable()->after('last_po_count');
            $table->unsignedInteger('raised_not_created_units')->nullable()->after('raised_not_created_days');
            $table->date('earliest_expected_date')->nullable()->after('raised_not_created_units');
            $table->unsignedInteger('earliest_expected_count')->nullable()->after('earliest_expected_date');
            $table->date('longest_waiting_date')->nullable()->after('earliest_expected_count');
            $table->unsignedInteger('longest_waiting_count')->nullable()->after('longest_waiting_date');
            $table->unsignedInteger('delayed_po')->nullable()->after('longest_waiting_count');
            $table->string('bottleneck_stage')->nullable()->after('delayed_po');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'orders_3d', 'units_3d', 'orders_7d', 'units_7d', 'orders_14d', 'units_14d',
                'demand_as_of', 'last_in_date', 'last_in_count', 'last_out_date', 'last_out_count',
                'last_po_date', 'last_po_count', 'raised_not_created_days', 'raised_not_created_units',
                'earliest_expected_date', 'earliest_expected_count', 'longest_waiting_date',
                'longest_waiting_count', 'delayed_po', 'bottleneck_stage',
            ]);
        });
    }
};

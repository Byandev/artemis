<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_orders_to_location_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Denormalised off the order so the RTS page / shop / team filters
            // still apply to the rollup. Not constrained: pancake_orders.page_id
            // is nullable (Webcake orders arrive without a page).
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();

            // The day the order reached its outcome — delivered_at when delivered,
            // returning_at when returned. This is how RTS analytics already buckets
            // an order into a period, so the rollup and the live query agree.
            $table->date('date');

            // Pancake's own province id ("63_598") and the name as it recorded it,
            // hyphenated and inconsistently cased. Both come from
            // public/ph_provinces.json, which is also where the readable label lives.
            $table->string('province_id', 32)->nullable();
            $table->string('province_name')->nullable();

            // Island group from the same list. Denormalised so the map can group by
            // region without a lookup per row.
            $table->string('region', 32)->nullable();

            // GADM feature id resolved at build time (App\Support\PhilippineGeo) so
            // the heat map shades polygons without re-matching names per request.
            $table->string('gadm_province_gid', 24)->nullable();

            // Counts and value side by side. Only delivered/returned orders are
            // rolled up, so `orders` = delivered_count + returning_count and
            // `sales` = delivered_amount + returning_amount. Amounts sum the
            // order's final_amount, matching what page_daily_records calls sales.
            $table->unsignedInteger('orders')->default(0);
            $table->decimal('sales', 15, 2)->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->decimal('delivered_amount', 15, 2)->default(0);
            $table->unsignedInteger('returning_count')->default(0);
            $table->decimal('returning_amount', 15, 2)->default(0);

            $table->timestamps();

            // Short, explicit names — the generated ones for the three-column
            // indexes would run past MySQL's 64-character identifier limit.
            $table->index(['workspace_id', 'date'], 'pol_ws_date_idx');
            $table->index(['workspace_id', 'date', 'page_id'], 'pol_ws_date_page_idx');
            $table->index(['workspace_id', 'date', 'shop_id'], 'pol_ws_date_shop_idx');
            $table->index(['workspace_id', 'date', 'gadm_province_gid'], 'pol_ws_date_province_idx');
            $table->index(['workspace_id', 'date', 'region'], 'pol_ws_date_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_orders_to_location_daily_records');
    }
};

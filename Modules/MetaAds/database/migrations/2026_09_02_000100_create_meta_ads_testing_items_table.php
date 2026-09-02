<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A campaign or ad set that has been put under test — one row per thing
     * being tracked. The daily numbers hang off it in
     * meta_ads_testing_daily_records.
     *
     * item_type/item_id follow the same shape the optimization tables already
     * use for "a campaign or an ad set": no foreign key, because the id is the
     * Meta id and is the primary key of meta_ads_campaigns / meta_ads_sets, so
     * Campaign::find() / AdSet::find() resolve it directly.
     */
    public function up(): void
    {
        Schema::create('meta_ads_testing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->enum('item_type', ['campaign', 'ad_set']);
            $table->unsignedBigInteger('item_id');

            $table->timestamps();

            // One entry per campaign/ad set per workspace — re-adding a thing
            // already under test should update, not duplicate.
            $table->unique(['workspace_id', 'item_type', 'item_id'], 'mati_ws_item_unique');
            $table->index(['item_type', 'item_id'], 'mati_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_testing_items');
    }
};

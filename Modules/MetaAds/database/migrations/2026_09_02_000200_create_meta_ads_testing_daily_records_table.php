<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per tested item per calendar day.
     *
     * `date` is the calendar day; `day` is where that day falls in the test —
     * day 1, day 2, day 3 — so runs that started on different dates still line
     * up against each other.
     *
     * `roas` is stored rather than derived, matching page_daily_records: the
     * figure is read far more often than it is written, and keeping it on the
     * row means the tracker can sort and filter on it in SQL.
     */
    public function up(): void
    {
        Schema::create('meta_ads_testing_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ads_testing_item_id', 'matdr_item_id')
                ->constrained('meta_ads_testing_items', indexName: 'matdr_item_index')
                ->cascadeOnDelete();

            $table->date('date');
            $table->unsignedSmallInteger('day')->nullable();

            $table->decimal('sales', 15, 2)->nullable();
            $table->decimal('ad_spent', 15, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();

            $table->timestamps();

            // Upsert key: one record per tested item per day.
            $table->unique(['meta_ads_testing_item_id', 'date'], 'matdr_item_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_testing_daily_records');
    }
};

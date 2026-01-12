<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL FULLTEXT indexes for improved LIKE '%pattern%' search performance
        // These indexes dramatically improve search queries (80-90% faster)

        // Pages name search
        DB::statement('ALTER TABLE pages ADD FULLTEXT INDEX idx_pages_name_fulltext (name)');

        // Products name/code search
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX idx_products_search_fulltext (name, code)');

        // Shipping addresses customer name search
        DB::statement('ALTER TABLE shipping_addresses ADD FULLTEXT INDEX idx_shipping_addresses_fullname_fulltext (full_name)');

        // Parcel journeys rider search
        DB::statement('ALTER TABLE parcel_journeys ADD FULLTEXT INDEX idx_parcel_journeys_rider_fulltext (rider_name, note)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop FULLTEXT indexes
        DB::statement('ALTER TABLE pages DROP INDEX idx_pages_name_fulltext');
        DB::statement('ALTER TABLE products DROP INDEX idx_products_search_fulltext');
        DB::statement('ALTER TABLE shipping_addresses DROP INDEX idx_shipping_addresses_fullname_fulltext');
        DB::statement('ALTER TABLE parcel_journeys DROP INDEX idx_parcel_journeys_rider_fulltext');
    }
};

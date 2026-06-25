<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Gencys-synced inventory items have no product, so product_id is nullable.
        DB::statement('ALTER TABLE inventory_items MODIFY product_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_items MODIFY product_id BIGINT UNSIGNED NOT NULL');
    }
};

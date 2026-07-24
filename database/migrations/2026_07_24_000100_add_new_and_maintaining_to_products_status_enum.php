<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('New','Testing','Scaling','Maintaining','Failed','Inactive') NOT NULL DEFAULT 'Testing'");
    }

    public function down(): void
    {
        // Rows on a status that is about to disappear would otherwise be
        // truncated to an empty string by MySQL. Park them on Testing — the
        // column default and the closest neutral stage.
        DB::statement("UPDATE products SET status = 'Testing' WHERE status IN ('New','Maintaining')");

        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('Scaling','Testing','Failed','Inactive') NOT NULL DEFAULT 'Testing'");
    }
};

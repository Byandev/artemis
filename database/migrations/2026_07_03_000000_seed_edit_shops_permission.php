<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the newly added "Edit Shops" permission
        // (and any other new enum cases) are inserted into the permissions table.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

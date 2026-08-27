<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the newly added "View Gencys Sync"
        // permission lands in the permissions table. It still has to be granted
        // to roles before anyone sees the Sync Runs page.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

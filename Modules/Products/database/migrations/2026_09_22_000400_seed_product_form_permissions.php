<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the new "View Product Forms" and
        // "Manage Product Forms" permissions (and any other new enum cases)
        // land in the permissions table, where the roles screen can hand them
        // out. Deployments run migrations, not seeders.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

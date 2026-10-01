<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the new "View Target Markets" and
        // "Manage Target Markets" permissions land in the permissions table,
        // where the roles screen can hand them out. Deployments run
        // migrations, not seeders.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

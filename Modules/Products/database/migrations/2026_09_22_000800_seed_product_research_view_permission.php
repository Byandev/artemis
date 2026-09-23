<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the new "View RDP Builder"
        // permission lands in the permissions table, where the roles screen
        // can hand it out. Deployments run migrations, not seeders.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

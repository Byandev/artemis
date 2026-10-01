<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the newly added "View Call Logs"
        // permission (and any other new enum cases) land in the permissions
        // table, where the roles screen can hand it out.
        (new PermissionSeeder)->run();
    }

    public function down(): void {}
};

<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the newly added "Manage Go Tyme
        // Balance" permission — the grant behind the page's Create Account
        // button — lands in the permissions table.
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Deliberately empty: rolling back must not strip a role of its grants.
    }
};

<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the newly added "View Go Tyme Balance"
        // permission lands in the permissions table. No role is granted it —
        // handing out the new S&M page is a deliberate edit in the role editor.
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Deliberately empty: rolling back must not strip a role of its grants.
    }
};

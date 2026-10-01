<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the permission seeder so the new "View New Creatives Tracker"
        // permission lands in the permissions table. Nobody is granted it —
        // handing it out is a deliberate edit in the role editor.
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Deliberately empty: rolling back must not strip a role of its grants.
    }
};

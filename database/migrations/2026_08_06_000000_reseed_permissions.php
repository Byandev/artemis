<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Reconcile the permissions table with the Permission enum: the seeder
        // upserts on name, so it adds any case missing from an environment and
        // corrects the category on the ones already there. Safe to re-run.
        (new PermissionSeeder)->run();
    }

    // Deliberately empty: the seeder only ever adds or corrects rows, and the
    // permissions it writes are the app's own definitions, not this migration's
    // to take away. Rolling back must not strip a role of its grants.
    public function down(): void {}
};

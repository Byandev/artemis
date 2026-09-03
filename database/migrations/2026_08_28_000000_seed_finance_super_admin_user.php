<?php

use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-run the admin user seeder so the finance@artemis.ph super admin
        // is created on every environment. The seeder uses firstOrCreate, so
        // existing accounts are left untouched.
        (new AdminUserSeeder)->run();
    }

    public function down(): void {}
};

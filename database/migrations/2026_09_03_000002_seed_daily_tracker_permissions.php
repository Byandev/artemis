<?php

use Database\Seeders\DailyTrackerItemSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Land the new "View Daily Tracker" / "Manage Daily Tracker" permissions
        // (and any other new enum cases) in the permissions table.
        (new PermissionSeeder)->run();

        // Give every S&M workspace the default deliverables, so the page opens
        // with the checklist rather than an empty board. Skips any workspace
        // that already has items.
        (new DailyTrackerItemSeeder)->run();
    }

    public function down(): void {}
};

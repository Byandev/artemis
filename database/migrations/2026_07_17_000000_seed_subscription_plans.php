<?php

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the subscription plan catalog on deploy, so it's present without a
     * separate `db:seed` step. The seeder is idempotent (updateOrInsert keyed on
     * `code`), so this is safe to run against a database that already has plans.
     */
    public function up(): void
    {
        (new SubscriptionPlanSeeder)->run();
    }

    public function down(): void
    {
        // Reference data — nothing to roll back.
    }
};

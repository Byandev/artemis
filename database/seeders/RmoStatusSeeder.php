<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Support\RmoDefaultStatuses;
use Illuminate\Database\Seeder;

/**
 * Gives every existing workspace the default CX / rider statuses. New
 * workspaces get them on creation (Workspace::booted), so this is for the ones
 * that predate the statuses:
 *
 *   php artisan db:seed --class=RmoStatusSeeder
 */
class RmoStatusSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::query()->each(fn (Workspace $workspace) => RmoDefaultStatuses::seed($workspace));
    }
}

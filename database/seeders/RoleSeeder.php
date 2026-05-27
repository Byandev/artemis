<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Full access to all workspace settings and members.',
            ],
            [
                'name' => 'editor',
                'description' => 'Can edit content but cannot manage workspace settings.',
            ],
            [
                'name' => 'member',
                'description' => 'Standard access to workspace features.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}

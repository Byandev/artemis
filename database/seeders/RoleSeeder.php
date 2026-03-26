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
                'role' => 'admin', // This is the identifier/slug
                'description' => 'Full access to all workspace settings and members.',
            ],
            [
                'role' => 'editor',
                'description' => 'Can edit content but cannot manage workspace settings.',
            ],
            [
                'role' => 'member',
                'description' => 'Standard access to workspace features.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['role' => $role['role']], $role);
        }
    }
}

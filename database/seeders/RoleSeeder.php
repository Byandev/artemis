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
                'display_name' => 'Administrator',
                'role' => 'admin', // This is the identifier/slug
                'description' => 'Full access to all workspace settings and members.',
            ],
            [
                'display_name' => 'Editor',
                'role' => 'editor',
                'description' => 'Can edit content but cannot manage workspace settings.',
            ],
            [
                'display_name' => 'Member',
                'role' => 'member',
                'description' => 'Standard access to workspace features.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['role' => $role['role']], $role);
        }
    }
}
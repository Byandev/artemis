<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkspaceUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // Create Member User
        $member = User::firstOrCreate(
            ['email' => 'member@gmail.com'],
            [
                'name' => 'Member User',
                'password' => Hash::make('member123'),
                'email_verified_at' => now(),
            ]
        );

        // Create a Workspace owned by Admin
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'default-workspace'],
            [
                'name' => 'Default Workspace',
                'description' => 'Default workspace for testing',
                'owner_id' => $admin->id,
            ]
        );

        // Add Admin to workspace as owner
        if (!$workspace->users()->where('user_id', $admin->id)->exists()) {
            $workspace->users()->attach($admin->id, ['role' => 'owner']);
        }

        // Add Member to workspace as member
        if (!$workspace->users()->where('user_id', $member->id)->exists()) {
            $workspace->users()->attach($member->id, ['role' => 'member']);
        }

        $this->command->info('✅ Users and Workspace seeded successfully!');
        $this->command->table(
            ['Email', 'Password', 'Role'],
            [
                ['admin@gmail.com', 'admin123', 'owner'],
                ['member@gmail.com', 'member123', 'member'],
            ]
        );
    }
}

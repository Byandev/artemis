<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'admin1@gmail.com'],
            [
                'name' => 'System Admin',
                'password' => bcrypt('password123'),
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}

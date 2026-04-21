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
        \App\Models\User::create([
            'name' => 'System Admin',
            'email' => 'admin1@gmail.com',
            'password' => bcrypt('password123'),
            'IsSuperAdmin' => true,
            'email_verified_at' => now(),
        ]);
    }
}

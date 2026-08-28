<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin1@gmail.com'],
            [
                'name' => 'System Admin',
                'password' => bcrypt('password123'),
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $finance = User::firstOrCreate(
            ['email' => 'finance@artemis.ph'],
            [
                'name' => 'Finance Admin',
                'password' => bcrypt('password123'),
                'is_super_admin' => true,
            ]
        );

        // email_verified_at is not mass assignable, and the admin routes sit
        // behind the "verified" middleware — so set it explicitly.
        if (! $finance->hasVerifiedEmail()) {
            $finance->forceFill(['email_verified_at' => now()])->save();
        }
    }
}

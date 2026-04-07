<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // For local development, seed all demo data from a single seeder file.
        if (app()->environment('local')) {
            $this->call(LocalFeatureDemoSeeder::class);

            return;
        }

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Seed Ads Manager sample data (commented out - use sync command instead)
        // $this->call(AdsManagerSeeder::class);
    }
}

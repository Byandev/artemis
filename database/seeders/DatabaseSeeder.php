<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\RTSAnalyticsSeeder;
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
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Seed RTS analytics sample data
        $this->call(RTSAnalyticsSeeder::class);

        // Seed Ads Manager sample data (commented out - use sync command instead)
        // $this->call(AdsManagerSeeder::class);
    }
}

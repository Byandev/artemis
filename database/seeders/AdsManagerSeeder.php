<?php

namespace Database\Seeders;

use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\FacebookAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AdsManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (or you can specify a specific user)
        $user = User::first();
        
        if (!$user) {
            $this->command->error('No users found. Please create a user first.');
            return;
        }

        // Get the first workspace
        $workspace = Workspace::first();
        
        if (!$workspace) {
            $this->command->error('No workspace found. Please create a workspace first.');
            return;
        }

        // Create a Facebook Account
        $facebookAccount = FacebookAccount::firstOrCreate(
            ['email' => 'test@facebook.com'],
            [
                'user_id' => $user->id,
                'name' => 'Test Facebook Account',
                'email' => 'test@facebook.com',
                'picture_url' => 'https://via.placeholder.com/150',
                'access_token' => 'fake_access_token_for_testing',
            ]
        );

        // Attach Facebook account to workspace
        if (!$workspace->facebookAccounts()->where('facebook_account_id', $facebookAccount->id)->exists()) {
            $workspace->facebookAccounts()->attach($facebookAccount->id);
        }

        $this->command->info('Facebook Account created/found: ' . $facebookAccount->name);

        // Create Ad Accounts
        $adAccounts = [
            [
                'name' => 'Ad Account - Electronics Store',
                'currency' => 'USD',
                'country_code' => 'US',
                'status' => 1,
            ],
            [
                'name' => 'Ad Account - Fashion Brand',
                'currency' => 'USD',
                'country_code' => 'US',
                'status' => 1,
            ],
            [
                'name' => 'Ad Account - Tech Gadgets',
                'currency' => 'EUR',
                'country_code' => 'DE',
                'status' => 1,
            ],
        ];

        foreach ($adAccounts as $accountData) {
            $adAccount = AdAccount::firstOrCreate(
                ['name' => $accountData['name']],
                $accountData
            );

            // Link Ad Account to Facebook Account
            if (!$facebookAccount->adAccounts()->where('ad_account_id', $adAccount->id)->exists()) {
                $facebookAccount->adAccounts()->attach($adAccount->id);
            }

            $this->command->info('Ad Account created/found: ' . $adAccount->name);

            // Create Campaigns for each Ad Account
            $this->createCampaigns($adAccount);
        }

        $this->command->info('✅ Ads Manager seeder completed successfully!');
    }

    private function createCampaigns(AdAccount $adAccount): void
    {
        $statuses = ['ACTIVE', 'PAUSED', 'ARCHIVED'];
        
        $campaigns = [
            [
                'name' => 'Black Friday Sale 2025',
                'status' => 'ACTIVE',
                'daily_budget' => 5000, // $50.00 in cents
                'start_time' => now()->subDays(10),
                'end_time' => now()->addDays(5),
            ],
            [
                'name' => 'Holiday Season Campaign',
                'status' => 'ACTIVE',
                'daily_budget' => 10000, // $100.00 in cents
                'start_time' => now()->subDays(5),
                'end_time' => now()->addDays(25),
            ],
            [
                'name' => 'New Year Special',
                'status' => 'PAUSED',
                'daily_budget' => 7500, // $75.00 in cents
                'start_time' => now()->addDays(10),
                'end_time' => now()->addDays(20),
            ],
            [
                'name' => 'Winter Collection Launch',
                'status' => 'ACTIVE',
                'daily_budget' => 15000, // $150.00 in cents
                'start_time' => now()->subDays(15),
                'end_time' => null, // Ongoing
            ],
            [
                'name' => 'Flash Sale - Weekend',
                'status' => 'ARCHIVED',
                'daily_budget' => 3000, // $30.00 in cents
                'start_time' => now()->subDays(30),
                'end_time' => now()->subDays(28),
            ],
        ];

        foreach ($campaigns as $campaignData) {
            $campaignData['ad_account_id'] = $adAccount->id;
            
            Campaign::firstOrCreate(
                [
                    'name' => $campaignData['name'],
                    'ad_account_id' => $adAccount->id,
                ],
                $campaignData
            );
        }

        $this->command->info("  └─ Created 5 campaigns for {$adAccount->name}");
    }
}

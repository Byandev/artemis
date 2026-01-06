<?php

namespace App\Console\Commands;

use App\Jobs\FetchAdAccounts;
use App\Models\FacebookAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;

class SyncSampleFacebookAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-sample-facebook-account {user} {workspace}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sample Facebook account data for testing. Fetches ad accounts, campaigns, and 2 months of ad records.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the token from env
        $token = env('FACEBOOK_SAMPLE_ACCOUNT_TOKEN');
        
        if (!$token) {
            $this->error('❌ FACEBOOK_SAMPLE_ACCOUNT_TOKEN not found in .env file!');
            $this->info('Please add it to your .env file:');
            $this->info('FACEBOOK_SAMPLE_ACCOUNT_TOKEN=your_token_here');
            return 1;
        }

        // Get user
        $userId = $this->argument('user');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("❌ User with ID {$userId} not found!");
            return 1;
        }

        // Get workspace
        $workspaceSlug = $this->argument('workspace');
        $workspace = Workspace::where('slug', $workspaceSlug)->first();
        
        if (!$workspace) {
            $this->error("❌ Workspace '{$workspaceSlug}' not found!");
            return 1;
        }

        $this->info("👤 User: {$user->name} (ID: {$user->id})");
        $this->info("🏢 Workspace: {$workspace->name} ({$workspace->slug})");
        $this->newLine();

        // Create or update Facebook Account
        $this->info('🔄 Creating/Updating Facebook Account...');
        
        $facebookAccount = FacebookAccount::updateOrCreate(
            ['user_id' => $user->id, 'email' => 'sample@facebook.com'],
            [
                'name' => 'Sample Facebook Account',
                'access_token' => $token,
                'picture_url' => 'https://via.placeholder.com/150',
            ]
        );

        $this->info("✅ Facebook Account created/updated: {$facebookAccount->name}");

        // Attach to workspace if not already attached
        if (!$workspace->facebookAccounts()->where('facebook_account_id', $facebookAccount->id)->exists()) {
            $workspace->facebookAccounts()->attach($facebookAccount->id);
            $this->info("✅ Attached Facebook Account to workspace");
        }

        // Dispatch the job to fetch ad accounts (which will trigger campaigns and ad records)
        $this->newLine();
        $this->info('🚀 Dispatching job to fetch Ad Accounts...');
        $this->info('📊 This will fetch:');
        $this->info('   - Ad Accounts');
        $this->info('   - Campaigns');
        $this->info('   - Ad Records (2 months of data)');
        $this->newLine();
        
        dispatch(new FetchAdAccounts($facebookAccount));

        $this->info('✅ Job dispatched successfully!');
        $this->newLine();
        $this->warn('⏳ Make sure Horizon is running to process the jobs:');
        $this->info('   php artisan horizon');
        $this->newLine();
        $this->info('📈 You can monitor the job progress in Horizon dashboard.');

        return 0;
    }
}

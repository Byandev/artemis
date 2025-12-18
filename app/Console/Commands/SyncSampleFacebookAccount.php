<?php

namespace App\Console\Commands;

use App\Jobs\FetchAdAccounts;
use App\Models\FacebookAccount;
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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        FacebookAccount::get()
            ->each(function (FacebookAccount $account) {
                dispatch(new FetchAdAccounts($account));
            });

        dd('Done');

        $userId = $this->argument('user');
        $workspaceId = $this->argument('workspace');

        $facebookAccount = FacebookAccount::updateOrCreate([
            'id' => '599663289850440',
        ], [
            'user_id' => $userId,
            'name' => 'JM Mulingbayan',
            'email' => 'bmulingbayan.ecomm.meta@gmail.com',
            'picture_url' => 'https://platform-lookaside.fbsbx.com/platform/profilepic/?asid=599663289850440&height=50&width=50&ext=1768394268&hash=AT9DbSgfJVRmiRxNjo6i-NnQ',
            'access_token' => config('services.facebook.sample_account_token'),
        ]);

        $workspace = Workspace::find($workspaceId);

        $facebookAccount->workspaces()->sync($workspace->id);

        dispatch(new FetchAdAccounts($facebookAccount));
    }
}

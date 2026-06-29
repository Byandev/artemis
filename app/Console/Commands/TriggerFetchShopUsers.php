<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopUsers;

class TriggerFetchShopUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-shops-users {--workspace= : Limit to a single workspace (id or slug)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch FetchShopUsers jobs for shops that have pages, optionally scoped to one workspace';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Shop::whereHas('pages');

        if ($workspace = $this->option('workspace')) {
            $model = Workspace::query()
                ->when(
                    is_numeric($workspace),
                    fn ($q) => $q->where('id', $workspace),
                    fn ($q) => $q->where('slug', $workspace),
                )
                ->first();

            if (! $model) {
                $this->error("Workspace [{$workspace}] not found.");

                return self::FAILURE;
            }

            $query->where('workspace_id', $model->id);
        }

        $count = 0;

        $query->each(function (Shop $shop) use (&$count) {
            dispatch(new FetchShopUsers($shop))->onQueue('pancake');
            $count++;
        });

        $this->info("Dispatched FetchShopUsers for {$count} shop(s).");

        return self::SUCCESS;
    }
}

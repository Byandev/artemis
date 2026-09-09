<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopOrders;

class TriggerFetchShopOrders extends Command
{
    protected $signature = 'trigger-fetch-shop-orders
        {id? : Sync only this shop ID}
        {--workspace= : Sync only shops in this workspace ID}
        {--shipped : Force a shipped-orders pull (filter_status[]=2) regardless of the hour}';

    protected $description = 'Sync Pancake shop orders (all sources, incl. Webcake). At 9/12/15/18/21 it pulls shipped orders (filter_status[]=2); otherwise it pulls orders updated since orders_last_synced_at. Use --shipped to force the shipped pull and --workspace to scope to one workspace.';

    public function handle()
    {
        $workspace = null;

        if ($this->option('workspace')) {
            $workspace = Workspace::find($this->option('workspace'));

            if (! $workspace) {
                $this->error("Workspace #{$this->option('workspace')} not found.");

                return self::FAILURE;
            }
        }

        // At the scheduled hours it pulls shipped orders; --shipped forces it on.
        $shipped = $this->option('shipped')
            || in_array((int) now()->format('G'), [9, 12, 15, 18, 21], true);

        Shop::whereNotNull('orders_last_synced_at')
            ->whereNotNull('pos_token')
            ->whereHas('pages', fn ($query) => $query->where('status', 'active'))
            ->when($this->argument('id'), fn ($query, $id) => $query->where('id', $id))
            ->when($workspace, fn ($query, $workspace) => $query->where('workspace_id', $workspace->id))
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function (Shop $shop) use ($shipped) {
                dispatch(new FetchShopOrders(
                    $shop,
                    1,
                    Carbon::parse($shop->orders_last_synced_at)->subDays(1)->unix(),
                    Carbon::now()->unix(),
                    false,
                ))->onQueue('pancake');
            });

        return self::SUCCESS;
    }
}

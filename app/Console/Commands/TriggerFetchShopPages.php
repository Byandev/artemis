<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopPages;

class TriggerFetchShopPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-shop-pages
        {--workspace= : Limit to a single workspace (id or slug)}
        {--shop= : Limit to a single shop id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue a page-list sync for every stored shop that has a POS token, pulling its pages from Pancake.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Shop::whereNotNull('pos_token')
            ->when($this->option('shop'), fn ($q, $id) => $q->where('id', $id));

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

        $query->orderBy('id')->each(function (Shop $shop) use (&$count) {
            dispatch(new FetchShopPages($shop))->onQueue('pancake');
            $count++;
        });

        $this->info("Queued FetchShopPages for {$count} shop(s) on the 'pancake' queue.");

        return self::SUCCESS;
    }
}

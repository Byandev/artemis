<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopOrders;

class RefreshWorkspaceShopOrders extends Command
{
    protected $signature = 'pancake:refresh-shop-orders
        {workspace : Workspace ID or slug}
        {--shop= : Only refresh this shop ID}
        {--months=3 : How many months back to re-pull}';

    protected $description = 'Refresh Pancake shop orders (all sources, incl. Webcake) for a workspace by re-pulling the last N months and dispatching FetchShopOrders to the pancake queue. Optionally scope to one shop.';

    public function handle(): int
    {
        $workspaceArg = $this->argument('workspace');

        $workspace = Workspace::query()
            ->when(
                is_numeric($workspaceArg),
                fn ($q) => $q->where('id', $workspaceArg),
                fn ($q) => $q->where('slug', $workspaceArg),
            )
            ->first();

        if (! $workspace) {
            $this->error("Workspace [{$workspaceArg}] not found.");

            return self::FAILURE;
        }

        $startTime = now()->subMonths((int) $this->option('months'))->unix();
        $endTime = now()->unix();

        $shops = Shop::where('workspace_id', $workspace->id)
            ->whereHas('pages', fn ($query) => $query
                ->whereNotNull('pos_token')
                ->where('status', 'active'))
            ->when($this->option('shop'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('name')
            ->get();

        if ($shops->isEmpty()) {
            $this->warn('No shops with active, connected pages found for this workspace.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Workspace "%s" · last %d month(s) · %d shop(s)',
            $workspace->name,
            (int) $this->option('months'),
            $shops->count(),
        ));

        foreach ($shops as $shop) {
            // Reset the watermark so the column reflects the in-progress re-pull; the
            // job restores it when the full sync finishes.
            $shop->update(['orders_last_synced_at' => null]);

            FetchShopOrders::dispatch($shop, 1, $startTime, $endTime, false)
                ->onQueue('pancake');

            $this->line("  • dispatched: {$shop->name}");
        }

        $this->newLine();
        $this->info("Dispatched order refresh for {$shops->count()} shop(s) to the 'pancake' queue.");
        $this->line("Monitor progress in Horizon (/horizon) — watch the 'pancake' queue.");

        return self::SUCCESS;
    }
}

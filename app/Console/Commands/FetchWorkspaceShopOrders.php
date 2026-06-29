<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopOrders;
use Throwable;

class FetchWorkspaceShopOrders extends Command
{
    protected $signature = 'pancake:fetch-shop-orders
        {workspace : Workspace ID to sync}
        {--start= : Start date (Y-m-d), inclusive}
        {--end= : End date (Y-m-d), inclusive}
        {--shop= : Only sync this shop ID}';

    protected $description = 'Backfill Pancake shop orders (all sources, incl. Webcake) for a workspace within a date range by dispatching FetchShopOrders to the pancake queue.';

    public function handle(): int
    {
        $workspace = Workspace::find($this->argument('workspace'));

        if (! $workspace) {
            $this->error("Workspace #{$this->argument('workspace')} not found.");

            return self::FAILURE;
        }

        if (! $this->option('start') || ! $this->option('end')) {
            $this->error('--start and --end are required (Y-m-d).');

            return self::FAILURE;
        }

        try {
            $start = Carbon::parse($this->option('start'))->startOfDay();
            $end = Carbon::parse($this->option('end'))->endOfDay();
        } catch (Throwable $e) {
            $this->error('Invalid --start / --end date. Use Y-m-d.');

            return self::FAILURE;
        }

        if ($start->greaterThan($end)) {
            $this->error('--start must be on or before --end.');

            return self::FAILURE;
        }

        $shops = Shop::where('workspace_id', $workspace->id)
            ->whereNotNull('pos_token')
            ->whereHas('pages', fn ($query) => $query->where('status', 'active'))
            ->when($this->option('shop'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('name')
            ->get();

        if ($shops->isEmpty()) {
            $this->warn('No shops with active, connected pages found for this workspace.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Workspace "%s" · %s → %s · %d shop(s)',
            $workspace->name,
            $start->toDateString(),
            $end->toDateString(),
            $shops->count(),
        ));

        foreach ($shops as $shop) {
            FetchShopOrders::dispatch($shop, 1, $start->unix(), $end->unix(), false)
                ->onQueue('pancake');

            $this->line("  • dispatched: {$shop->name}");
        }

        $this->newLine();
        $this->info("Dispatched order sync for {$shops->count()} shop(s) to the 'pancake' queue.");
        $this->line("Monitor progress in Horizon (/horizon) — watch the 'pancake' queue.");

        return self::SUCCESS;
    }
}

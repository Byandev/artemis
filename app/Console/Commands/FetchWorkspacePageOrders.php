<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageOrders;
use Throwable;

class FetchWorkspacePageOrders extends Command
{
    protected $signature = 'pancake:fetch-page-orders
        {workspace : Workspace ID to sync}
        {--start= : Start date (Y-m-d), inclusive}
        {--end= : End date (Y-m-d), inclusive}
        {--page= : Only sync this page ID}';

    protected $description = 'Fetch Pancake page orders for a workspace within a date range by dispatching FetchPageOrders to the pancake queue.';

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

        $pages = Page::where('workspace_id', $workspace->id)
            ->whereNotNull('pos_token')
            ->whereNotNull('shop_id')
            ->where('status', 'active')
            ->when($this->option('page'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('name')
            ->get();

        if ($pages->isEmpty()) {
            $this->warn('No active, connected pages found for this workspace.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Workspace "%s" · %s → %s · %d page(s)',
            $workspace->name,
            $start->toDateString(),
            $end->toDateString(),
            $pages->count(),
        ));

        foreach ($pages as $page) {
            FetchPageOrders::dispatch($page, 1, $start->unix(), $end->unix(), false)
                ->onQueue('pancake');

            $this->line("  • dispatched: {$page->name}");
        }

        $this->newLine();
        $this->info("Dispatched order sync for {$pages->count()} page(s) to the 'pancake' queue.");
        $this->line("Monitor progress in Horizon (/horizon) — watch the 'pancake' queue.");

        return self::SUCCESS;
    }
}

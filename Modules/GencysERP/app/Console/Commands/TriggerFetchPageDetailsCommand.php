<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\GencysERP\Support\PageDetailsSync;

class TriggerFetchPageDetailsCommand extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-page-details
        {--page=* : Limit to specific Gencys page id(s); repeat (--page=1 --page=2) or comma-separate. Omit for all pages}
        {--delay=30 : Seconds to stagger each queued page by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use for an n8n test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Trigger n8n for each workspace with ERP credentials to fetch its Gencys pages\' detail (POS token, shop id, etc.), one page per call';

    public function handle(PageDetailsSync $pageDetails): int
    {
        // The queued/scheduled path only runs on production. --sync is an explicit
        // manual action (e.g. hitting an n8n test-mode webhook locally), so it
        // bypasses the guard without needing --force too.
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        $webhookUrl = $this->option('webhook')
            ?: config('services.n8n.gencys_page_details_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n page details webhook URL is not configured (services.n8n.gencys_page_details_webhook_url). Pass --webhook= to override.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');
        $delay = max(0, (int) $this->option('delay'));
        $pageIds = $this->pageIds();

        if (! empty($pageIds)) {
            $this->info('Limiting to Gencys page id(s): '.implode(', ', $pageIds));
        }

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $this->info(($sync ? 'Sending' : 'Queueing').' page details…');

        $dispatched = 0;

        foreach ($workspaces as $workspace) {
            $pages = $pageDetails->dispatchForWorkspace(
                $workspace,
                $pageIds,
                $sync,
                $delay,
                $this->option('webhook') ?: null,
            );

            if (empty($pages)) {
                $this->warn("Skipping workspace {$workspace->id} — no synced pages.");

                continue;
            }

            $dispatched += count($pages);
            $this->info("{$workspace->name} (ID: {$workspace->id}) — ".count($pages).' page(s): '.implode(', ', $pages));
        }

        $this->newLine();
        $this->info(($sync ? 'Sent' : 'Queued')." {$dispatched} page(s).");

        return self::SUCCESS;
    }

    /**
     * Parse the --page option into a list of Gencys page ids. Accepts repeated
     * flags (--page=1 --page=2) and/or comma-separated values (--page=1,2).
     *
     * @return int[]
     */
    private function pageIds(): array
    {
        return collect((array) $this->option('page'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

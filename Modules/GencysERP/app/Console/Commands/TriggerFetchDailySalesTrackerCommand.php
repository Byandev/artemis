<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\GencysERP\Jobs\FetchDailySalesTrackerJob;

class TriggerFetchDailySalesTrackerCommand extends Command
{
    /** Number of trailing days fetched when no date options are given. */
    private const DEFAULT_DAYS = 3;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gencys-erp:trigger-fetch-daily-sales-tracker {--delay=240} {--date=} {--start-date=} {--end-date=} {--sync : POST to n8n immediately instead of queueing on the erp worker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger the n8n webhook for each workspace to fetch its Gencys ERP daily sales tracker';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webhookUrl = config('services.n8n.gencys_daily_sales_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.gencys_daily_sales_webhook_url).');

            return self::FAILURE;
        }

        $delay = (int) $this->option('delay');

        try {
            $dates = $this->resolveDates();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Only workspaces wired for ERP automation: ERP credentials configured
        // and at least one API key for the n8 n callback to authenticate with.
        $workspaces = $this->eligibleWorkspaces()
            ->with('apiKeys')
            ->orderBy('id')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');

        $dateLabel = count($dates) === 1
            ? "date: {$dates[0]}"
            : count($dates).' dates: '.$dates[0].' → '.end($dates);

        $this->info($sync
            ? "Sending {$workspaces->count()} workspace(s) synchronously for {$dateLabel}"
            : "Dispatching {$workspaces->count()} workspace(s) with {$delay}s delay between jobs for {$dateLabel}");

        $callbackBase = rtrim(config('app.url'), '/');
        $callbackUrl = "$callbackBase/api/v1/public/gencys/daily-sales-tracker";

        $dispatched = 0;
        $skipped = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            if (! $apiKey) {
                $this->warn("Skipping workspace {$workspace->id} — no API key found.");
                $skipped++;

                continue;
            }

            foreach ($dates as $date) {
                $data = [
                    'workspace_id' => $workspace->id,
                    'workspace_slug' => $workspace->slug,
                    'workspace_api_key' => $apiKey->reveal(),
                    // ERP login the n8n pipeline authenticates with (password decrypted).
                    'erp_username' => $workspace->erp_username,
                    'erp_password' => $workspace->erp_password,
                    'date' => $date,
                    'webhook_url' => $callbackUrl,
                ];

                if ($sync) {
                    // Run inline so the webhook fires immediately — no erp worker needed.
                    FetchDailySalesTrackerJob::dispatchSync($webhookUrl, $data);
                    $this->info("Sent for workspace {$workspace->name} (ID: {$workspace->id}) for {$date}");
                } else {
                    $jobDelaySeconds = $dispatched * $delay;

                    FetchDailySalesTrackerJob::dispatch($webhookUrl, $data)
                        ->delay(now()->addSeconds($jobDelaySeconds));

                    $this->info("Dispatched for workspace {$workspace->name} (ID: {$workspace->id}) for {$date} (delay: {$jobDelaySeconds}s)");
                }

                $dispatched++;
            }
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Resolve the list of dates (formatted m/d/Y) to fetch.
     *
     * Supports a single --date, a --start-date/--end-date range (inclusive),
     * or defaults to the last 3 days (ending yesterday) when none are given.
     *
     * @return array<int, string>
     *
     * @throws \InvalidArgumentException on invalid or inverted input
     */
    private function resolveDates(): array
    {
        $start = $this->option('start-date');
        $end = $this->option('end-date');

        // Range mode: both bounds required so the intent is unambiguous.
        if ($start || $end) {
            if (! $start || ! $end) {
                throw new \InvalidArgumentException('Both --start-date and --end-date must be provided for a date range.');
            }

            try {
                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->startOfDay();
            } catch (\Exception) {
                throw new \InvalidArgumentException("Invalid date range provided: {$start} → {$end}");
            }

            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException("--start-date ({$start}) must not be after --end-date ({$end}).");
            }

            $dates = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dates[] = $date->format('m/d/Y');
            }

            return $dates;
        }

        // Explicit single date.
        if ($this->option('date')) {
            try {
                $date = Carbon::parse($this->option('date'));
            } catch (\Exception) {
                throw new \InvalidArgumentException("Invalid date provided: {$this->option('date')}");
            }

            return [$date->format('m/d/Y')];
        }

        // No date options at all: default to the last 3 days, ending yesterday.
        $dates = [];
        for ($i = self::DEFAULT_DAYS; $i >= 1; $i--) {
            $dates[] = Carbon::today()->subDays($i)->format('m/d/Y');
        }

        return $dates;
    }

    /** Workspaces wired for ERP automation. Kept for readability/testability. */
    private function eligibleWorkspaces(): Builder
    {
        return Workspace::query()
            ->whereNotNull('erp_username')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys');
    }
}

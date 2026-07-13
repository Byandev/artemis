<?php

namespace Modules\GencysERP\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Jobs\FetchInternDailyRecordsJob;
use Modules\GencysERP\Models\GencysSyncRun;

class TriggerFetchInternDailyRecordsCommand extends Command
{
    protected $signature = 'gencys-erp:trigger-fetch-intern-daily-records
        {--date= : The record date in Y-m-d format (defaults to yesterday)}
        {--intern=* : Limit to specific Gencys intern id(s); repeat (--intern=1 --intern=2) or comma-separate. Omit for all interns}
        {--delay=300 : Seconds to stagger each queued chunk by}
        {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}
        {--sync : POST to the webhook immediately in-process instead of queueing (use for an n8n test-mode webhook)}
        {--force : Run outside production (by default this command only runs on production)}';

    protected $description = 'Trigger n8n for each workspace with ERP credentials to fetch its Gencys interns\' daily records';

    public function handle(): int
    {
        // The queued/scheduled path only runs on production. --sync is an explicit
        // manual action (e.g. hitting an n8n test-mode webhook locally), so it
        // bypasses the guard without needing --force too.
        if (! app()->environment('production') && ! $this->option('force') && ! $this->option('sync')) {
            $this->warn('This command only runs on production. Re-run with --force (or --sync) to override (current environment: '.app()->environment().').');

            return self::SUCCESS;
        }

        $webhookUrl = $this->option('webhook')
            ?: config('services.n8n.gencys_intern_daily_records_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n intern daily records webhook URL is not configured (services.n8n.gencys_intern_daily_records_webhook_url). Pass --webhook= to override.');

            return self::FAILURE;
        }

        $dateOption = $this->option('date');

        try {
            $date = $dateOption
                ? Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay()
                : Carbon::yesterday();
        } catch (\Exception) {
            $this->error("Invalid date '{$dateOption}'. Expected format: Y-m-d (e.g. 2026-07-06).");

            return self::FAILURE;
        }

        $recordDate = $date->format('m/d/Y');
        $sync = (bool) $this->option('sync');
        $delay = max(0, (int) $this->option('delay'));
        $internIds = $this->internIds();

        if (! empty($internIds)) {
            $this->info('Limiting to Gencys intern id(s): '.implode(', ', $internIds));
        }

        $workspaces = Workspace::whereNotNull('erp_username')
            ->where('erp_username', '!=', '')
            ->whereNotNull('erp_password')
            ->whereHas('apiKeys')
            ->with(['apiKeys', 'interns' => function ($query) use ($internIds) {
                // Only interns pulled from the ERP (they have a Gencys intern id).
                $query->where('active', true)->whereNotNull('intern_id');

                if (! empty($internIds)) {
                    $query->whereIn('intern_id', $internIds);
                }
            }])
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found with ERP credentials and an API key.');

            return self::SUCCESS;
        }

        $this->info(($sync ? 'Sending' : 'Queueing')." intern daily records for {$recordDate}…");

        $callbackBase = rtrim(config('services.n8n.callback_base_url') ?: config('app.url'), '/');
        $callbackUrl = "{$callbackBase}/api/v1/public/gencys/intern-daily-records";

        $dispatched = 0;

        foreach ($workspaces as $workspace) {
            $apiKey = $workspace->apiKeys->first();

            if ($workspace->interns->isEmpty()) {
                $this->warn("Skipping workspace {$workspace->id} — no synced interns.");

                continue;
            }

            // One webhook call per intern: each intern gets its own job, its own
            // pending sync run, and its own payload carrying a single intern_id.
            foreach ($workspace->interns as $intern) {
                $run = GencysSyncRun::start(
                    $workspace->id,
                    null,
                    GencysSyncRun::TYPE_INTERN_DAILY_RECORDS,
                    ['intern_id' => $intern->intern_id, 'date' => $recordDate],
                );

                $data = [
                    'workspace_id' => $workspace->id,
                    'api_key' => $apiKey->reveal(),
                    'erp_username' => $workspace->erp_username,
                    'erp_password' => $workspace->erp_password,
                    'webhook_url' => $callbackUrl,
                    'intern_id' => $intern->intern_id,
                    'sync_run_id' => $run->id,
                    'date' => $recordDate,
                ];

                if ($sync) {
                    FetchInternDailyRecordsJob::dispatchSync($webhookUrl, $data, [$run->id]);
                } else {
                    FetchInternDailyRecordsJob::dispatch($webhookUrl, $data, [$run->id])
                        ->delay(now()->addSeconds($dispatched * $delay));
                }

                $dispatched++;
                $this->info("{$workspace->name} (ID: {$workspace->id}) — intern {$intern->intern_id}");
            }
        }

        $this->newLine();
        $this->info(($sync ? 'Sent' : 'Queued')." {$dispatched} intern(s).");

        return self::SUCCESS;
    }

    /**
     * Parse the --intern option into a list of Gencys intern ids. Accepts repeated
     * flags (--intern=1 --intern=2) and/or comma-separated values (--intern=1,2).
     *
     * @return int[]
     */
    private function internIds(): array
    {
        return collect((array) $this->option('intern'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

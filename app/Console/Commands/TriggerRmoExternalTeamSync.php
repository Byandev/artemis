<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\RmoExternalTeamSheet;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks n8n to read the external RMO team's Google Sheet for a day and post the
 * rows back to /api/v1/public/rmo-orders/external-team, which flags and
 * auto-tags the matching RMO rows (see RmoExternalTeamSheet).
 *
 * The payload carries no secret: n8n authenticates its callback with the
 * workspace API key it holds as its own credential.
 */
class TriggerRmoExternalTeamSync extends Command
{
    protected $signature = 'rmo:trigger-external-team-sync
                            {--workspace=* : Workspace id or slug; repeat or comma-separate for several. Defaults to every workspace with external team sync on}
                            {--date= : Delivery date in Y-m-d format. Defaults to today}
                            {--webhook= : Override the n8n webhook URL (e.g. point at a test-mode webhook)}';

    protected $description = 'Trigger the n8n webhook that syncs the external RMO team\'s Google Sheet back into RMO management.';

    public function handle(): int
    {
        $webhookUrl = $this->option('webhook') ?: config('services.n8n.rmo_external_team_webhook_url');

        if (empty($webhookUrl)) {
            $this->error('n8n webhook URL is not configured (services.n8n.rmo_external_team_webhook_url). Pass --webhook= to override.');

            return self::FAILURE;
        }

        $workspaces = $this->targetWorkspaces();

        if ($workspaces === null) {
            return self::FAILURE;
        }

        if ($workspaces->isEmpty()) {
            $this->info('No workspaces have external team sync enabled.');

            return self::SUCCESS;
        }

        try {
            $date = $this->option('date')
                ? CarbonImmutable::createFromFormat('Y-m-d', $this->option('date'))->toDateString()
                : now()->toDateString();
        } catch (Throwable) {
            $this->error('--date must be in Y-m-d format.');

            return self::FAILURE;
        }

        $callbackBase = rtrim(config('services.n8n.callback_base_url') ?: config('app.url'), '/');
        $failed = false;

        foreach ($workspaces as $workspace) {
            // Settings → RMO management decides which workspaces are synced;
            // naming one with --workspace doesn't get around that.
            if (! $workspace->rmoExternalTeamSyncEnabled()) {
                $this->warn("{$workspace->name} (ID: {$workspace->id}) doesn't have external team sync enabled — skipped.");
                $failed = true;

                continue;
            }

            $apiKey = $workspace->apiKeys()->first();

            if (! $apiKey) {
                $this->warn("{$workspace->name} (ID: {$workspace->id}) has no API key for n8n to call back with — skipped.");
                $failed = true;

                continue;
            }

            $response = Http::timeout(30)->post($webhookUrl, [
                'workspace_id' => $workspace->id,
                'date' => $date,
                'sheet_url' => $workspace->rmoSetting->external_team_sheet_url,
                'sheet_id' => RmoExternalTeamSheet::sheetId($workspace->rmoSetting->external_team_sheet_url),
                'api_key' => $apiKey->reveal(),
                'callback_url' => "{$callbackBase}/api/v1/public/rmo-orders/external-team",
            ]);

            if ($response->failed()) {
                $this->error("{$workspace->name} (ID: {$workspace->id}) — n8n responded {$response->status()}.");
                $failed = true;

                continue;
            }

            $this->info("{$workspace->name} (ID: {$workspace->id}) — triggered for {$date}.");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The workspaces named with --workspace, or — as the scheduler runs it —
     * every workspace that switched external team sync on. Null when a named
     * workspace doesn't exist.
     *
     * @return Collection<int, Workspace>|null
     */
    private function targetWorkspaces(): ?Collection
    {
        $keys = collect((array) $this->option('workspace'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique();

        if ($keys->isEmpty()) {
            return Workspace::query()
                ->whereHas('rmoSetting', fn ($q) => $q
                    ->where('enable_external_team_sync', true)
                    ->whereNotNull('external_team_sheet_url'))
                ->with('rmoSetting')
                ->get();
        }

        $workspaces = collect();

        foreach ($keys as $key) {
            $workspace = Workspace::where('id', $key)->orWhere('slug', $key)->first();

            if (! $workspace) {
                $this->error("Workspace {$key} not found.");

                return null;
            }

            $workspaces->push($workspace);
        }

        return $workspaces;
    }
}

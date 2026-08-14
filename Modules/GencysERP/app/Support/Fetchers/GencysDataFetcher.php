<?php

namespace Modules\GencysERP\Support\Fetchers;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Contracts\FetchesGencysData;

/**
 * Shared ground for the {@see FetchesGencysData} strategies: option access,
 * webhook and callback resolution, the payload every type sends, and the one
 * guarded path to the queue.
 *
 * These used to be three separate console commands. They share the parts worth
 * sharing — the production gate, workspace eligibility, webhook resolution,
 * sync-run bookkeeping — but genuinely differ in date handling, chunking and
 * payload shape, which is why each type keeps its own subclass rather than
 * living behind flags in one 400-line handle().
 */
abstract class GencysDataFetcher implements FetchesGencysData
{
    public function __construct(
        protected Command $command,
        protected array $options,
    ) {}

    /** Config key holding this type's dedicated n8n webhook URL. */
    abstract protected function webhookConfigKey(): string;

    /** Path on Artemis that n8n posts this type's results back to. */
    abstract protected function callbackPath(): string;

    /**
     * The n8n webhook to hit.
     *
     * The per-type URLs still win when set, so one type can be pointed at a
     * test-mode webhook during the cutover. Once every type runs through the
     * consolidated workflow only N8N_WEBHOOK_URL matters.
     */
    public function webhookUrl(): ?string
    {
        return $this->option('webhook')
            ?: config($this->webhookConfigKey())
            ?: config('services.n8n.webhook_url');
    }

    /**
     * The keys every type sends: who is asking, the ERP login n8n authenticates
     * with (password decrypted), and where to post the results back.
     */
    protected function basePayload(Workspace $workspace): array
    {
        return [
            // Selects the branch in the consolidated n8n workflow.
            'type' => $this->type(),
            'workspace_id' => $workspace->id,
            'workspace_api_key' => $workspace->apiKeys->first()->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'webhook_url' => $this->callbackUrl(),
        ];
    }

    protected function callbackUrl(): string
    {
        $base = rtrim((string) (config('services.n8n.callback_base_url') ?: config('app.url')), '/');

        return $base.$this->callbackPath();
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    protected function batchId(): ?int
    {
        return $this->option('batch') ? (int) $this->option('batch') : null;
    }

    protected function isSync(): bool
    {
        return (bool) $this->option('sync');
    }

    /** @return int[] */
    protected function itemIds(): array
    {
        return (array) $this->option('item', []);
    }

    /**
     * Items eligible for this workspace, already filtered by --item.
     *
     * Parent items are grouping placeholders with no ERP SKU, so they never
     * sync. A specific --item selection wins over the active-only default, so a
     * single item can be re-synced (or tested) even when it's inactive.
     */
    protected function itemConstraint(): callable
    {
        $itemIds = $this->itemIds();

        return function ($query) use ($itemIds) {
            $query->where('is_parent', false);

            if (empty($itemIds)) {
                $query->where('is_active', true);
            } else {
                $query->whereIn('id', $itemIds);
            }
        };
    }

    /**
     * Hand one call to the queue, or send it inline under --sync.
     *
     * Wrapped because under --sync the job runs here and rethrows whatever the
     * webhook did, and an unhandled throw takes the rest of the sweep with it —
     * one unreachable webhook on the first chunk used to abort the command
     * before the remaining chunks and types had opened a single run. The job has
     * already failed its own runs by then, so swallowing the throw only costs
     * the exit code, which the batch's own counters carry instead.
     */
    protected function send(object $job, int $delaySeconds = 0): void
    {
        try {
            if ($this->isSync()) {
                dispatch_sync($job);
            } else {
                dispatch($job)->delay(now()->addSeconds($delaySeconds));
            }
        } catch (\Throwable $e) {
            $this->info("dispatch failed: {$e->getMessage()}");
        }
    }

    protected function info(string $message): void
    {
        $this->command->getOutput()->writeln("  {$message}");
    }

    /**
     * Parse a Y-m-d option into a Carbon date.
     *
     * @throws \InvalidArgumentException
     */
    protected function parseDate(string $value, string $option): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Exception) {
            throw new \InvalidArgumentException(
                "Invalid --{$option} '{$value}'. Expected format: Y-m-d (e.g. 2026-06-24)."
            );
        }
    }
}

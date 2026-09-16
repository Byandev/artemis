<?php

namespace App\Console\Commands;

use App\Enums\IntegrationService;
use App\Jobs\FetchWelleProgress;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Welle\WelleClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class FetchWelleDailyRecords extends Command
{
    protected $signature = 'welle:fetch-daily-records
                            {--workspace= : Limit to members of one workspace, by id or slug. Defaults to every Welle-enabled workspace.}
                            {--user= : Limit to one user, by id or email.}
                            {--days= : Fetch a trailing window of N days ending today instead of the current week. Reads the Welle range endpoint, the only way to reach days the current week no longer covers.}
                            {--sync : Fetch inline instead of queueing, reporting each user as it goes. For local checks and one-off runs.}';

    protected $description = "Fetch each connected user's Welle progress and store it as daily ESC records. Reads the current week unless --days asks for a longer window.";

    public function handle(): int
    {
        if (! WelleClient::isConfigured()) {
            $this->error('No Welle base URL configured. Set WELLE_BASE_URL before running this.');

            return self::FAILURE;
        }

        $workspaceId = $this->targetWorkspaceId();

        if ($workspaceId === false) {
            return self::FAILURE;
        }

        $userId = $this->targetUserId();

        if ($userId === false) {
            return self::FAILURE;
        }

        $userIds = $this->connectedUserIds($workspaceId, $userId);

        if ($userIds->isEmpty()) {
            $this->warn('No users with a connected Welle account in a Welle-enabled workspace.');

            return self::SUCCESS;
        }

        [$start, $end] = $this->targetWindow();

        if (! $this->option('sync')) {
            $userIds->each(fn ($id) => FetchWelleProgress::dispatch((int) $id, $start, $end));

            $this->info("Dispatched {$userIds->count()} FetchWelleProgress job(s){$this->windowLabel($start, $end)}.");

            return self::SUCCESS;
        }

        return $this->fetchInline($userIds, $start, $end);
    }

    /**
     * The window to read, or nulls for the current week.
     *
     * `--days=30` means the thirty days ending today, today included — so
     * `--days=1` is today alone and `--days=2` is today and yesterday.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function targetWindow(): array
    {
        if (! $this->option('days')) {
            return [null, null];
        }

        $days = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::today();

        return [$today->subDays($days - 1)->toDateString(), $today->toDateString()];
    }

    private function windowLabel(?string $start, ?string $end): string
    {
        return $start === null ? '' : " covering {$start} → {$end}";
    }

    /**
     * Run every fetch in this process, one user at a time.
     *
     * Each user is reported as they land and a failure does not abandon the
     * rest — one bad account should not cost everyone else their week, and
     * seeing every distinct error beats seeing only the first.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function fetchInline(Collection $userIds, ?string $start, ?string $end): int
    {
        $failed = 0;

        foreach ($userIds as $id) {
            try {
                FetchWelleProgress::dispatchSync((int) $id, $start, $end);
                $this->line("  <fg=green>✔</> user {$id}");
            } catch (Throwable $e) {
                $failed++;
                $this->line("  <fg=red>✘</> user {$id} — {$e->getMessage()}");
            }
        }

        if ($failed > 0) {
            $this->error(sprintf(
                'Fetched %d of %d user(s) — %d failed.',
                $userIds->count() - $failed,
                $userIds->count(),
                $failed,
            ));

            return self::FAILURE;
        }

        $this->info($start === null
            ? "Fetched the current week for {$userIds->count()} user(s)."
            : "Fetched {$start} → {$end} for {$userIds->count()} user(s).");

        return self::SUCCESS;
    }

    /**
     * Users with a connected Welle account who are a member of at least one
     * workspace with the module switched on. A workspace that turned Welle off
     * contributes nobody.
     *
     * @return Collection<int, int>
     */
    private function connectedUserIds(?int $workspaceId, ?int $userId): Collection
    {
        return User::query()
            ->whereHas('integrations', fn ($query) => $query->forService(IntegrationService::Welle))
            ->whereHas('workspaces', fn ($query) => $query
                ->where('welle_module_enabled', true)
                ->when($workspaceId, fn ($q, $id) => $q->where('workspaces.id', $id)))
            ->when($userId, fn ($query, $id) => $query->whereKey($id))
            ->pluck('id');
    }

    /**
     * The workspace to limit members to, or null for all of them.
     *
     * Returns false when --workspace names nothing — someone scoping a run did
     * not ask for every workspace instead.
     */
    private function targetWorkspaceId(): int|false|null
    {
        $option = $this->option('workspace');

        if (! $option) {
            return null;
        }

        $id = Workspace::query()
            ->where(fn ($query) => $query->where('slug', $option)->orWhere('id', $option))
            ->value('id');

        if ($id === null) {
            $this->error("No workspace matches '{$option}'.");

            return false;
        }

        return (int) $id;
    }

    /** The single user to fetch for, or null for all of them. */
    private function targetUserId(): int|false|null
    {
        $option = $this->option('user');

        if (! $option) {
            return null;
        }

        $id = User::query()
            ->where(fn ($query) => $query->where('email', $option)->orWhere('id', $option))
            ->value('id');

        if ($id === null) {
            $this->error("No user matches '{$option}'.");

            return false;
        }

        return (int) $id;
    }
}

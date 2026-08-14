<?php

namespace Modules\GencysERP\Support\Fetchers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Jobs\FetchDailySalesTrackerJob;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Daily sales tracker: one run per workspace per date. Unlike the other two
 * types this isn't per inventory item, so there's no chunking — the whole day's
 * tracker comes back in one call.
 */
class DailySalesTrackerFetcher extends GencysDataFetcher
{
    /** Trailing days fetched when no date options are given. */
    private const DEFAULT_DAYS = 3;

    /** Seconds between queued calls. */
    private const DEFAULT_DELAY_SECONDS = 240;

    public function type(): string
    {
        return GencysSyncRun::TYPE_DAILY_SALES_TRACKER;
    }

    protected function webhookConfigKey(): string
    {
        return 'services.n8n.gencys_daily_sales_webhook_url';
    }

    protected function callbackPath(): string
    {
        return '/api/v1/public/gencys/daily-sales-tracker';
    }

    public function loadWorkspaces(Builder $query): Collection
    {
        return $query->with('apiKeys')->orderBy('id')->get();
    }

    public function dispatch(Collection $workspaces): int
    {
        $dates = $this->resolveDates();
        $webhookUrl = $this->webhookUrl();
        $batchId = $this->batchId();
        $delay = (int) ($this->option('delay') ?? self::DEFAULT_DELAY_SECONDS);

        $label = count($dates) === 1
            ? $dates[0]
            : $dates[0].' – '.end($dates);

        $this->info("daily sales tracker for {$label}");

        $dispatched = 0;

        foreach ($workspaces as $workspace) {
            foreach ($dates as $date) {
                // A run per workspace/date, its id handed to n8n so the callback
                // can echo it back for an exact match.
                $run = GencysSyncRun::start(
                    $workspace->id,
                    null,
                    $this->type(),
                    ['date' => $date],
                    $batchId,
                );

                $data = array_merge($this->basePayload($workspace), [
                    'workspace_slug' => $workspace->slug,
                    'date' => $date,
                    'sync_run_id' => $run->id,
                ]);

                $this->send(
                    new FetchDailySalesTrackerJob($webhookUrl, $data, [$run->id]),
                    $dispatched * $delay,
                );

                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Supports a single --date, an inclusive --start-date/--end-date range, or
     * defaults to the last 3 days ending yesterday.
     *
     * @return array<int, string> dates formatted m/d/Y
     *
     * @throws \InvalidArgumentException
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

            $startDate = $this->parseDate($start, 'start-date');
            $endDate = $this->parseDate($end, 'end-date');

            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException("--start-date ({$start}) must not be after --end-date ({$end}).");
            }

            $dates = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dates[] = $date->format('m/d/Y');
            }

            return $dates;
        }

        if ($single = $this->option('date')) {
            return [$this->parseDate($single, 'date')->format('m/d/Y')];
        }

        $dates = [];
        for ($i = self::DEFAULT_DAYS; $i >= 1; $i--) {
            $dates[] = Carbon::today()->subDays($i)->format('m/d/Y');
        }

        return $dates;
    }
}

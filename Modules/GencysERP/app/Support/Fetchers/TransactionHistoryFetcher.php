<?php

namespace Modules\GencysERP\Support\Fetchers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Transaction history: one pass per date in the range, items chunked so n8n
 * logs into the ERP once per chunk rather than once per item.
 */
class TransactionHistoryFetcher extends ChunkedItemFetcher
{
    public function type(): string
    {
        return GencysSyncRun::TYPE_TRANSACTION_HISTORY;
    }

    protected function webhookConfigKey(): string
    {
        return 'services.n8n.transaction_history_webhook_url';
    }

    protected function callbackPath(): string
    {
        return '/api/v1/public/inventory-items/transactions/bulk-sync';
    }

    public function loadWorkspaces(Builder $query): Collection
    {
        return $query->with(['apiKeys', 'inventoryItems' => $this->itemConstraint()])->get();
    }

    protected function chunkDelayMinutes(): int
    {
        return 2;
    }

    protected function makeJob(string $webhookUrl, array $data, array $syncRunIds): object
    {
        return new FetchInventoryItemTransactionHistory($webhookUrl, $data, $syncRunIds);
    }

    protected function describe(array $passes): string
    {
        $first = $passes[0]['date'];
        $last = end($passes)['date'];

        return 'transaction history for '.($first === $last ? $first : "{$first} – {$last}");
    }

    /**
     * One pass per date. --date is a shortcut for a single day and wins over the
     * range options; otherwise the range runs from --start-date (default:
     * yesterday) through --end-date (default: today).
     */
    protected function passes(): array
    {
        return $this->resolveDates()
            ->map(fn (Carbon $date) => ['date' => $date->format('m/d/Y')])
            ->all();
    }

    /**
     * @return Collection<int, Carbon>
     *
     * @throws \InvalidArgumentException
     */
    private function resolveDates(): Collection
    {
        if ($single = $this->option('date')) {
            return collect([$this->parseDate($single, 'date')]);
        }

        $start = ($startOption = $this->option('start-date'))
            ? $this->parseDate($startOption, 'start-date')
            : Carbon::yesterday();

        $end = ($endOption = $this->option('end-date'))
            ? $this->parseDate($endOption, 'end-date')
            : Carbon::today();

        if ($start->gt($end)) {
            throw new \InvalidArgumentException(
                "Start date ({$start->toDateString()}) must not be after end date ({$end->toDateString()})."
            );
        }

        $dates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }

        return $dates;
    }
}

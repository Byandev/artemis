<?php

namespace Modules\GencysERP\Support\Fetchers;

use Illuminate\Console\Command;
use Modules\GencysERP\Contracts\FetchesGencysData;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Builds the fetch strategy for a Gencys ERP data type. The only place that
 * knows which class serves which type, so the trigger command can work in terms
 * of {@see FetchesGencysData} and a new type is one arm of the match below.
 */
class GencysFetcherFactory
{
    /**
     * The types this factory can build, in dispatch order — also the list the
     * trigger command validates --type against.
     *
     * @return string[]
     */
    public function types(): array
    {
        return [
            GencysSyncRun::TYPE_TRANSACTION_HISTORY,
            GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
            GencysSyncRun::TYPE_PURCHASE_ORDER,
        ];
    }

    /**
     * @param  Command  $command  the console the fetcher writes its progress to
     * @param  array  $options  the trigger command's options, already normalised
     *
     * @throws \InvalidArgumentException
     */
    public function for(string $type, Command $command, array $options): FetchesGencysData
    {
        return match ($type) {
            GencysSyncRun::TYPE_TRANSACTION_HISTORY => new TransactionHistoryFetcher($command, $options),
            GencysSyncRun::TYPE_DAILY_SALES_TRACKER => new DailySalesTrackerFetcher($command, $options),
            GencysSyncRun::TYPE_PURCHASE_ORDER => new PurchaseOrderFetcher($command, $options),
            default => throw new \InvalidArgumentException("No Gencys ERP fetcher for type [{$type}]."),
        };
    }
}

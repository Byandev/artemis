<?php

namespace Modules\GencysERP\Support\SyncFlows;

use InvalidArgumentException;
use Modules\GencysERP\Models\GencysSyncRun;

/** Looks up the SyncFlow that knows how to build and send a given sync type. */
class SyncFlowRegistry
{
    /**
     * The sync types the batch queue owns, in the order the UI offers them.
     *
     * Page details are deliberately absent: they still fan out on the old
     * fixed-timer path. Adding them is a matter of writing a flow class and
     * listing it here.
     *
     * Not everything here is offered in the batch form — see
     * SyncFlow::offeredInBatchForm(). Intern daily records is queue-owned and
     * scheduled, but not something to raise by hand.
     *
     * @var array<string, class-string<SyncFlow>>
     */
    private const FLOWS = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY => TransactionHistoryFlow::class,
        GencysSyncRun::TYPE_PURCHASE_ORDER => PurchaseOrderFlow::class,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER => DailySalesTrackerFlow::class,
        GencysSyncRun::TYPE_INTERNS => InternFlow::class,
        GencysSyncRun::TYPE_INTERN_DAILY_RECORDS => InternDailyRecordFlow::class,
    ];

    public function for(string $syncType): SyncFlow
    {
        $flow = self::FLOWS[$syncType] ?? null;

        if (! $flow) {
            throw new InvalidArgumentException("No Gencys sync flow registered for '{$syncType}'.");
        }

        return app($flow);
    }

    public function has(string $syncType): bool
    {
        return isset(self::FLOWS[$syncType]);
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys(self::FLOWS);
    }

    /**
     * Every flow, keyed by sync type — used by the UI to list what can be synced.
     *
     * @return array<string, SyncFlow>
     */
    public function all(): array
    {
        return collect(self::FLOWS)
            ->map(fn (string $class) => app($class))
            ->all();
    }
}

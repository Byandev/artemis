<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Models\TestingItem;
use Modules\MetaAds\Services\TestingDailyRecordSync;
use Modules\MetaAds\Services\TestingItemResolver;

class SyncTestingDailyRecordsCommand extends Command
{
    protected $signature = 'metaads:sync-testing-records
                            {--workspace= : Only sync a single workspace id}';

    protected $description = 'Roll meta_ads_insights up into meta_ads_testing_daily_records for every tracked campaign / ad set.';

    public function handle(TestingDailyRecordSync $sync, TestingItemResolver $resolver): int
    {
        $workspaceId = $this->option('workspace');

        // Paused items are deliberately left out — they keep the history they
        // have and stop accruing days until someone resumes them.
        $items = TestingItem::active()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->get();

        if ($items->isEmpty()) {
            $this->info('Nothing under test — nothing to sync.');

            return self::SUCCESS;
        }

        // Re-resolve first: an item whose page or shop link only landed after
        // it was added picks up its product here.
        $resolved = $resolver->resolve($items);
        $written = $sync->sync($items);

        $this->info("Synced {$written} daily ".str('record')->plural($written)." across {$items->count()} tracked ".str('item')->plural($items->count()).", {$resolved} re-attributed.");

        return self::SUCCESS;
    }
}

<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Modules\MetaAds\Models\AdAccount;

/**
 * Gate job placed at the tail of a new account's backfill chain. Runs after
 * SyncCampaigns has populated the DB and only kicks off the (heavy) insights
 * backfill when the account actually has at least one campaign — empty accounts
 * skip the insights pulls entirely.
 */
class BackfillInsightsIfActive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Run on the dedicated Meta Ads Horizon queue (max 3 processes). */
    public $queue = 'meta-ads';

    public function __construct(
        public AdAccount $adAccount,
        public int $days,
    ) {}

    public function handle(): void
    {
        if ($this->days < 1 || ! $this->adAccount->campaigns()->exists()) {
            return;
        }

        $until = Carbon::today();
        $chain = [];

        for ($i = 0; $i < $this->days; $i++) {
            $chain[] = new SyncInsights($this->adAccount, $until->copy()->subDays($i)->toDateString());
        }

        Bus::chain($chain)->dispatch();
    }
}

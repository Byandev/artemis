<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireTrialSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Mark trial subscriptions as expired when trial_ends_at has passed';

    public function handle(): int
    {
        $expired = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->where('trial_ends_at', '<', Carbon::now())
            ->update(['status' => Subscription::STATUS_EXPIRED]);

        $this->info("Expired {$expired} trial subscription(s).");

        return self::SUCCESS;
    }
}

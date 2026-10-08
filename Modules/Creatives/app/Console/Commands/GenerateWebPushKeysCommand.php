<?php

namespace Modules\Creatives\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Prints a new VAPID key pair for Web Push. Generate once per environment and
 * keep it: changing the keys cuts off every browser already subscribed.
 */
class GenerateWebPushKeysCommand extends Command
{
    protected $signature = 'creatives:web-push-keys';

    protected $description = 'Generate VAPID keys for Creatives Tracker web push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Add these to .env (once per environment; changing them later unsubscribes every browser):');
        $this->newLine();
        $this->line('WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}

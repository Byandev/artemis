<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;

class SendTestDiscordNotification extends Command
{
    protected $signature = 'discord:test
        {message=🔔 Test notification from Artemis : The message content to send.}
        {--url= : Override the webhook URL (defaults to services.discord.webhook_url).}';

    protected $description = 'Send a sample notification to a Discord webhook to verify the integration.';

    public function handle(DiscordNotifier $discord): int
    {
        $message = $this->argument('message');
        $url = $this->option('url') ?: null;

        $this->info('Sending test notification to Discord...');

        $sent = $discord->send(
            $message,
            [],
            $url,
        );

        if (! $sent) {
            $this->error('Failed to send — check DISCORD_WEBHOOK_URL and the logs.');

            return self::FAILURE;
        }

        $this->info('Sent ✅');

        return self::SUCCESS;
    }
}

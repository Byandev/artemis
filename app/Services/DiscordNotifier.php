<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends messages to Discord via an incoming webhook.
 *
 *     app(DiscordNotifier::class)->send('Deploy finished ✅');
 *
 *     app(DiscordNotifier::class)->send('Budget alert', [
 *         'title' => 'Daily ad spend',
 *         'description' => 'Today is up 12% vs yesterday',
 *         'color' => 0xE67E22,
 *     ]);
 */
class DiscordNotifier
{
    /**
     * Post a message to a Discord channel webhook.
     *
     * @param  string  $content  Plain message content.
     * @param  array<string, mixed>  $embed  Optional Discord embed payload (title, description, color, fields, ...).
     * @param  string|null  $webhookUrl  Target webhook; falls back to services.discord.webhook_url.
     */
    public function send(string $content, array $embed = [], ?string $webhookUrl = null): bool
    {
        $webhookUrl ??= config('services.discord.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('Discord notification skipped: no webhook url configured.');

            return false;
        }

        $payload = ['content' => $content];

        if ($embed !== []) {
            $payload['embeds'] = [$embed];
        }

        $response = Http::timeout(30)->post($webhookUrl, $payload);

        if ($response->failed()) {
            Log::warning('Discord webhook call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}

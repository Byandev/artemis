<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a Discord incoming-webhook URL, i.e.
 *
 *     https://discord.com/api/webhooks/{id}/{token}
 *
 * A bare `url` rule accepts any URL, so a mistyped or unrelated link is stored
 * and only fails much later — silently, inside the queued notifier. This checks
 * the shape up front.
 *
 * Accepted hosts are Discord's own: discord.com, the ptb/canary subdomains, and
 * the legacy discordapp.com. An optional API version prefix (/api/v10/webhooks)
 * is allowed since Discord's UI hands those out too.
 */
class DiscordWebhookUrl implements ValidationRule
{
    private const PATTERN = '#^https://(ptb\.|canary\.)?discord(app)?\.com/api(/v\d+)?/webhooks/\d+/[\w-]+/?$#';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, trim($value))) {
            $fail('The :attribute must be a valid Discord webhook URL, e.g. https://discord.com/api/webhooks/123456789/abcdef.');
        }
    }
}

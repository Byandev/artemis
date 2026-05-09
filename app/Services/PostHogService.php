<?php

namespace App\Services;

use PostHog\PostHog;

class PostHogService
{
    public function capture(string $distinctId, string $event, array $properties = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        PostHog::capture([
            'distinctId' => $distinctId,
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    public function identify(string $distinctId, array $properties = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        PostHog::identify([
            'distinctId' => $distinctId,
            'properties' => $properties,
        ]);
    }

    private function enabled(): bool
    {
        return ! config('posthog.disabled') && (bool) config('posthog.api_key');
    }
}

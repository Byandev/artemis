<?php

namespace Modules\MetaAds\Support;

use App\Services\DiscordNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\AdAccount;
use Throwable;

/**
 * Posts to Discord the moment a sync gives up on an ad account. No schedule and
 * no digest — the sync itself sends, so an error shows up in the channel while
 * the run is still happening.
 *
 * Only real failures get here. The rate-limited and transient paths in
 * HandlesMetaSyncErrors release the job for a retry and heal on their own, so
 * posting those would report problems that are still fixing themselves.
 */
class SyncErrorNotifier
{
    /**
     * One expired token fails every job for every ad account under it, so the
     * same account is muted briefly after it reports — otherwise a single
     * outage arrives as dozens of identical posts.
     */
    private const MUTE_MINUTES = 15;

    public function __construct(private DiscordNotifier $discord) {}

    public function notify(?AdAccount $account, Throwable $e, string $job): void
    {
        $webhook = config('services.discord.meta_ads_webhook_url')
            ?: config('services.discord.webhook_url');

        if (empty($webhook) || $this->recentlyReported($account)) {
            return;
        }

        // The job has already failed; Discord being unreachable must not mask
        // the real error or burn a retry attempt.
        try {
            $this->discord->send('', $this->buildEmbed($account, $e, $job), $webhook);
        } catch (Throwable $alertFailure) {
            Log::warning('Meta sync error notification could not be delivered', [
                'exception' => $alertFailure,
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    /** True the first time through, then false for MUTE_MINUTES per account. */
    private function recentlyReported(?AdAccount $account): bool
    {
        if (! $account) {
            return false;
        }

        $key = "metaads:sync-error-reported:{$account->id}";

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addMinutes(self::MUTE_MINUTES));

        return false;
    }

    /**
     * Four levels, so the channel can act without opening Ads Manager first:
     * the headline says what broke, the body says what to do, the account line
     * says where, and Meta's raw codes sit last for the support ticket.
     *
     * @return array<string, mixed>
     */
    private function buildEmbed(?AdAccount $account, Throwable $e, string $job): array
    {
        $name = $account?->name ?: 'Unknown account';
        $status = $account?->account_status !== null ? (int) $account->account_status : null;
        $cause = SyncErrorCause::for($e, $status);

        $fields = [
            ['name' => 'Ad account', 'value' => $this->accountValue($account, $name), 'inline' => true],
            ['name' => 'Status', 'value' => $this->statusValue($status), 'inline' => true],
            ['name' => 'Sync', 'value' => class_basename($job), 'inline' => true],
        ];

        // Meta's own words, kept out of the description so the action stays the
        // first thing read. Only shown when it adds something.
        if ($message = trim($e->getMessage())) {
            $fields[] = ['name' => 'Meta says', 'value' => $this->clamp($message, 1024), 'inline' => false];
        }

        if ($meta = $this->metaErrorLine($e)) {
            $fields[] = ['name' => 'Reference', 'value' => $meta, 'inline' => false];
        }

        return [
            'title' => '🔴 '.$cause['headline'].' — '.$name,
            'description' => $cause['action'],
            'color' => 0xED4245,
            'fields' => $fields,
            'footer' => ['text' => 'metaads sync'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /** Deep-links into Ads Manager, so the fix is one click from the alert. */
    private function accountValue(?AdAccount $account, string $name): string
    {
        if (! $account) {
            return $name;
        }

        $link = "https://adsmanager.facebook.com/adsmanager/manage/campaigns?act={$account->id}";

        return "[Open in Ads Manager]({$link})\n`{$account->id}`";
    }

    private function statusValue(?int $status): string
    {
        $label = AccountStatus::label($status);

        return AccountStatus::isActive($status) ? $label : "⚠️ {$label}";
    }

    /** Meta's own identifiers — what a support ticket needs. */
    private function metaErrorLine(Throwable $e): ?string
    {
        if (! $e instanceof MetaGraphException) {
            return null;
        }

        $parts = array_filter([
            $e->errorCode !== null ? "code {$e->errorCode}" : null,
            $e->errorSubcode !== null ? "subcode {$e->errorSubcode}" : null,
            $e->errorType ?: null,
            $e->httpStatus !== null ? "HTTP {$e->httpStatus}" : null,
            $e->fbtraceId ? "fbtrace `{$e->fbtraceId}`" : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Discord drops an embed whole if the description runs over. */
    private function clamp(string $text, int $limit = 4096): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3).'...' : $text;
    }
}

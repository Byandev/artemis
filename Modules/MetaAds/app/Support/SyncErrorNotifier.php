<?php

namespace Modules\MetaAds\Support;

use App\Services\DiscordNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Throwable;

/**
 * Posts to Discord while a sync is running, the moment it gives up on an ad
 * account. No schedule and no digest command — the sync itself sends.
 *
 * The channel is read by clients, not engineers, so the post never carries an
 * error code, a job name or an fbtrace id: "OAuthException code 190" tells a
 * client nothing they can act on. What it carries instead is the Facebook
 * account, the ad accounts that stopped, and why in plain words. The technical
 * detail still goes to the log and the SyncRun for whoever debugs it.
 *
 * Only real failures get here. The rate-limited and transient paths in
 * HandlesMetaSyncErrors release the job for a retry and heal on their own.
 */
class SyncErrorNotifier
{
    /**
     * One expired token fails every job for every ad account under it, so the
     * Facebook account is muted briefly after it reports — otherwise a single
     * outage arrives as dozens of identical posts.
     */
    private const MUTE_MINUTES = 15;

    /** Ad accounts listed before the rest roll into "+N more". */
    private const MAX_LISTED = 10;

    public function __construct(private DiscordNotifier $discord) {}

    public function notify(?AdAccount $account, Throwable $e, string $job): void
    {
        $webhook = config('services.discord.meta_ads_webhook_url')
            ?: config('services.discord.webhook_url');

        if (empty($webhook) || ! $account) {
            return;
        }

        $owner = $account->metaUsers->first();

        if ($this->recentlyReported($owner?->id ?? $account->id)) {
            return;
        }

        // The job has already failed; Discord being unreachable must not mask
        // the real error or burn a retry attempt.
        try {
            $this->discord->send('', $this->buildEmbed($account, $owner), $webhook);
        } catch (Throwable $failure) {
            Log::warning('Meta sync error notification could not be delivered', [
                'exception' => $failure,
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    /** True the first time through, then false for MUTE_MINUTES. */
    private function recentlyReported(int|string $key): bool
    {
        $cacheKey = "metaads:sync-error-reported:{$key}";

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, now()->addMinutes(self::MUTE_MINUTES));

        return false;
    }

    /**
     * The Facebook account at the top, then every one of its ad accounts that
     * Meta is not serving, each showing the status Meta reports — Disabled,
     * Unsettled, In Grace Period — the same wording the ad-accounts table uses,
     * so the channel and the dashboard agree.
     *
     * @return array<string, mixed>
     */
    private function buildEmbed(AdAccount $failed, ?object $owner): array
    {
        $problems = $this->problemAccounts($failed, $owner);

        return [
            'title' => '⚠️ Meta Ads needs your attention',
            'description' => $owner
                ? "Facebook account: **{$owner->name}**"
                : 'These ad accounts stopped running.',
            'color' => 0xE67E22,
            'fields' => [[
                'name' => count($problems) === 1
                    ? '1 ad account affected'
                    : count($problems).' ad accounts affected',
                'value' => $this->buildList($problems),
                'inline' => false,
            ]],
            'footer' => ['text' => 'Meta Ads'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Every ad account under the same Facebook account that Meta is not
     * serving, plus the one that just failed. Keyed by id so an account that is
     * both is listed once.
     *
     * The failed account is included even when Meta still calls it active:
     * something stopped it, and an alert listing no accounts would leave the
     * reader with nothing to act on.
     *
     * @return array<string, array{name: string, id: string, status: string}>
     */
    private function problemAccounts(AdAccount $failed, ?object $owner): array
    {
        $rows = [];

        $candidates = $owner
            ? $owner->adAccounts()->get(['meta_ads_accounts.id', 'name', 'account_status'])
            : new Collection([$failed]);

        foreach ($candidates as $account) {
            $status = $account->account_status !== null ? (int) $account->account_status : null;

            if (AccountStatus::isActive($status)) {
                continue;
            }

            $rows[(string) $account->id] = [
                'name' => $account->name ?: 'Untitled ad account',
                'id' => (string) $account->id,
                'status' => AccountStatus::label($status),
            ];
        }

        // Meta may still call the failed account active while its sync is
        // broken. "Active" would read as nonsense on an alert, so this one case
        // says what actually happened instead of echoing the status.
        $rows[(string) $failed->id] ??= [
            'name' => $failed->name ?: 'Untitled ad account',
            'id' => (string) $failed->id,
            'status' => 'Sync error',
        ];

        return $rows;
    }

    /**
     * One line per ad account. Capped by count and by Discord's 1024-character
     * field limit — going over either drops the whole post, so the overflow is
     * stated rather than cut mid-line.
     *
     * @param  array<string, array{name: string, id: string, status: string}>  $problems
     */
    private function buildList(array $problems): string
    {
        $lines = collect($problems)
            ->take(self::MAX_LISTED)
            ->map(fn (array $p) => "**{$p['name']}** · `{$p['id']}`\n{$p['status']}")
            ->values();

        if (count($problems) > self::MAX_LISTED) {
            $lines->push('_+'.(count($problems) - self::MAX_LISTED).' more_');
        }

        $value = $lines->implode("\n\n");

        if (mb_strlen($value) <= 1024) {
            return $value;
        }

        $kept = [];
        $used = 0;

        foreach ($lines as $line) {
            if ($used + mb_strlen($line) + 2 > 1024 - 40) {
                break;
            }

            $kept[] = $line;
            $used += mb_strlen($line) + 2;
        }

        $dropped = count($problems) - count($kept);

        return implode("\n\n", $kept).($dropped > 0 ? "\n\n_+{$dropped} more_" : '');
    }
}

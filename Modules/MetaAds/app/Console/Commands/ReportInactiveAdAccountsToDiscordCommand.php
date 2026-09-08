<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\Workspace;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Support\AccountStatus;

/**
 * Posts the workspace's ad accounts that Meta no longer reports as Active —
 * disabled, unsettled, in grace period, closed, or never synced. These are the
 * accounts that have quietly stopped delivering, usually over billing or
 * policy, so they are worth a channel ping rather than a dashboard visit.
 */
class ReportInactiveAdAccountsToDiscordCommand extends Command
{
    /** Accounts listed per severity tier before the rest roll into "+N more". */
    private const MAX_PER_TIER = 10;

    protected $signature = 'metaads:report-inactive-accounts
        {--force : Send now, ignoring each workspace\'s configured send time.}
        {--workspace= : Limit to one workspace id or slug.}';

    protected $description = 'Post a Discord summary of ad accounts Meta does not report as Active, per workspace.';

    public function handle(DiscordNotifier $discord): int
    {
        $force = (bool) $this->option('force');
        $nowHHMM = now()->format('H:i');

        $workspaces = $this->workspacesDueNow($force, $nowHHMM);

        if ($workspaces->isEmpty()) {
            $this->info($force
                ? 'No workspace has the inactive-accounts report enabled with a webhook to post to.'
                : "No workspace is due an inactive-accounts report at {$nowHHMM}.");

            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($workspaces as $workspace) {
            $accounts = $this->inactiveAccountsFor($workspace);

            if ($accounts->isEmpty()) {
                $this->line("All ad accounts active for {$workspace->name} — skipped.");

                continue;
            }

            $sent = $discord->send('', $this->buildEmbed($workspace, $accounts),
                $workspace->metaAdsNotificationSetting->inactive_accounts_webhook_url);

            if ($sent) {
                $sentCount++;
            } else {
                $this->warn("Failed to send inactive-accounts report for workspace {$workspace->name} — check the webhook URL and logs.");
            }
        }

        $this->info("Inactive ad accounts report: sent to {$sentCount} workspace(s).");

        return self::SUCCESS;
    }

    /**
     * Workspaces that opted in: enabled, with their own webhook to post to, and
     * due at the current hour unless --force. A workspace with no settings row
     * has not opted in.
     *
     * @return Collection<int, Workspace>
     */
    private function workspacesDueNow(bool $force, string $nowHHMM): Collection
    {
        return Workspace::query()
            ->when($this->option('workspace'), function ($query, $workspace) {
                $query->where(fn ($q) => $q->where('id', $workspace)->orWhere('slug', $workspace));
            })
            ->whereHas('metaAdsNotificationSetting', function ($query) use ($force, $nowHHMM) {
                $query->where('inactive_accounts_enabled', true)
                    ->whereNotNull('inactive_accounts_webhook_url')
                    ->where('inactive_accounts_webhook_url', '!=', '')
                    ->unless($force, fn ($q) => $q->where('inactive_accounts_send_at', $nowHHMM));
            })
            ->with('metaAdsNotificationSetting:id,workspace_id,inactive_accounts_webhook_url')
            ->get(['id', 'name']);
    }

    /**
     * The workspace's ad accounts Meta does not report as Active. A null status
     * counts as not-active: it means Meta has never told us, which is itself
     * worth surfacing.
     *
     * @return Collection<int, AdAccount>
     */
    private function inactiveAccountsFor(Workspace $workspace): Collection
    {
        return AdAccount::query()
            ->forWorkspace($workspace)
            ->where(fn ($q) => $q->whereNull('account_status')->orWhere('account_status', '!=', AccountStatus::ACTIVE))
            ->orderBy('name')
            ->get(['id', 'name', 'account_status', 'currency', 'business_name', 'last_synced_at']);
    }

    /**
     * One embed per workspace, split into a field per severity tier so the
     * channel can see at a glance how many accounts actually need action
     * versus how many are merely closed. The embed takes the colour of the
     * worst tier present, so a red post in the channel means "someone has to
     * do something" without opening it.
     *
     * @param  Collection<int, AdAccount>  $accounts
     * @return array<string, mixed>
     */
    private function buildEmbed(Workspace $workspace, Collection $accounts): array
    {
        $grouped = $accounts->groupBy(
            fn (AdAccount $account) => AccountStatus::severity($this->statusOf($account))
        );

        // Worst-first, skipping tiers with nothing in them.
        $tiers = collect(AccountStatus::tiers())->filter(fn ($tier) => $grouped->has($tier))->values();

        $fields = $tiers->map(function (string $tier) use ($grouped) {
            $inTier = $grouped->get($tier);

            return [
                'name' => AccountStatus::heading($tier).' · '.$inTier->count(),
                'value' => $this->buildTierValue($inTier),
                'inline' => false,
            ];
        })->all();

        $worst = $tiers->first();

        return [
            'title' => $this->buildTitle($worst, $grouped->get($worst)->count()),
            'description' => $this->buildSummaryLine($grouped, $tiers),
            'color' => AccountStatus::color($worst),
            'fields' => $fields,
            'author' => ['name' => $workspace->name],
            'footer' => ['text' => 'metaads:report-inactive-accounts'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The headline verdict, not a raw total: with one disabled account among
     * five closed ones, "Action needed — 1 account" is the thing to read, and
     * the other five are context the fields already carry.
     */
    private function buildTitle(string $worst, int $count): string
    {
        return AccountStatus::heading($worst).' — '.$count.' '.Str::plural('account', $count);
    }

    /**
     * The full split, one level below the title: "1 action needed · 5 closed or
     * never synced". Omitted when there is only one tier, where it would just
     * restate the title.
     *
     * @param  Collection<string, Collection<int, AdAccount>>  $grouped
     * @param  Collection<int, string>  $tiers
     */
    private function buildSummaryLine(Collection $grouped, Collection $tiers): string
    {
        if ($tiers->count() < 2) {
            return '';
        }

        return $tiers->map(fn (string $tier) => $grouped->get($tier)->count().' '
            .mb_strtolower(AccountStatus::tierLabel($tier)))->implode(' · ');
    }

    /**
     * The account lines for one tier. Capped both by count and by Discord's
     * 1024-character field limit — going over either drops the whole embed, so
     * the overflow is stated ("+N more") rather than cut mid-line.
     *
     * @param  Collection<int, AdAccount>  $accounts
     */
    private function buildTierValue(Collection $accounts): string
    {
        $lines = $accounts->take(self::MAX_PER_TIER)->map(fn (AdAccount $a) => $this->accountLine($a));

        if ($accounts->count() > self::MAX_PER_TIER) {
            $lines->push('_+'.($accounts->count() - self::MAX_PER_TIER).' more_');
        }

        $value = $lines->implode("\n");

        if (mb_strlen($value) <= 1024) {
            return $value;
        }

        // Still too long: drop whole lines until it fits, keeping the count honest.
        $kept = [];
        $used = 0;

        foreach ($lines as $line) {
            // 40 chars of headroom for the "+N more" line we may still append.
            if ($used + mb_strlen($line) + 1 > 1024 - 40) {
                break;
            }

            $kept[] = $line;
            $used += mb_strlen($line) + 1;
        }

        $dropped = $accounts->count() - count($kept);

        return implode("\n", $kept).($dropped > 0 ? "\n_+{$dropped} more_" : '');
    }

    /**
     * Two levels per account. The first line is what the account is and why it
     * is listed — name (deep-linked into Ads Manager) and Meta's status, both
     * bold, because the status is the reason the row exists and reading it
     * should not mean scanning a run of dot-separated metadata.
     *
     * The second line is identity: the id, currency and business that tell two
     * similarly named accounts apart, plus how stale our copy of the status is.
     * The bare id is in backticks so Discord renders it as inline code — it is
     * the value someone pastes into Ads Manager or a support ticket, so it
     * needs to be selectable without picking up the surrounding punctuation,
     * and monospace reads lighter than the line above it.
     */
    private function accountLine(AdAccount $account): string
    {
        $name = $this->escapeMarkdown($account->name ?: 'Untitled account');
        $link = "https://adsmanager.facebook.com/adsmanager/manage/campaigns?act={$account->id}";
        $status = AccountStatus::label($this->statusOf($account));

        $identity = array_filter([
            '`'.$account->id.'`',
            $account->currency,
            $account->business_name ? $this->escapeMarkdown($account->business_name) : null,
            $this->syncNote($account),
        ]);

        return "**[{$name}]({$link})** — **{$status}**\n".implode(' · ', $identity);
    }

    /** How current the status is — the whole point of a "never synced" row. */
    private function syncNote(AdAccount $account): string
    {
        return $account->last_synced_at
            ? 'synced '.$account->last_synced_at->diffForHumans(short: true)
            : 'never synced';
    }

    private function statusOf(AdAccount $account): ?int
    {
        return $account->account_status !== null ? (int) $account->account_status : null;
    }

    /**
     * Account names are Meta-supplied, so underscores and asterisks in them
     * would otherwise turn the rest of the line italic or bold.
     */
    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/([*_~`|\\\\\[\]])/u', '\\\\$1', $text);
    }
}

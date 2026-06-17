<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\PageDailyBudgetRecord;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportPageBudgetsToDiscordCommand extends Command
{
    protected $signature = 'metaads:report-page-budgets
        {--date= : Report date (YYYY-MM-DD, defaults to today).}';

    protected $description = 'Send a per-page ad-spend budget summary for the day to Discord.';

    public function handle(DiscordNotifier $discord): int
    {
        if (empty(config('services.discord.webhook_url'))) {
            $this->info('Discord webhook not configured — skipping budget report.');

            return self::SUCCESS;
        }

        $today = $this->option('date') ?: Carbon::today()->toDateString();
        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        $records = PageDailyBudgetRecord::query()
            ->whereIn('date', [$today, $yesterday])
            ->with([
                'page' => fn ($q) => $q->withTrashed()->select('id', 'name', 'workspace_id'),
                'workspace:id,name',
            ])
            ->get(['id', 'page_id', 'workspace_id', 'date', 'budget']);

        $description = $this->buildDescription($records, $today, $yesterday);

        $sent = $discord->send('📊 Daily page budgets', [
            'title' => "Page budgets — {$today}",
            'description' => $description,
            'color' => 0x57F287,
            'footer' => ['text' => 'metaads:report-page-budgets'],
        ]);

        if (! $sent) {
            $this->error('Failed to send budget report to Discord — check DISCORD_WEBHOOK_URL and the logs.');

            return self::FAILURE;
        }

        $this->info("Sent budget report for {$today} ({$records->count()} record(s)).");

        return self::SUCCESS;
    }

    /**
     * Build the Discord embed body: pages grouped by workspace, each showing
     * today's budget and the difference vs yesterday, with a workspace total —
     * mirroring the Ad Spent Tracker. Capped to Discord's 4096-char limit.
     *
     * @param  Collection<int, PageDailyBudgetRecord>  $records
     */
    private function buildDescription($records, string $today, string $yesterday): string
    {
        if ($records->isEmpty()) {
            return "No page budgets were recorded for {$today}.";
        }

        $lines = [];

        foreach ($records->groupBy('workspace_id') as $group) {
            $workspaceName = $group->first()->workspace?->name ?? 'Unknown workspace';
            $lines[] = "**{$workspaceName}**";

            // Roll each page into today/yesterday totals so we can show the delta.
            $pages = [];
            foreach ($group as $rec) {
                $key = $rec->page_id;
                $pages[$key] ??= [
                    'name' => $rec->page?->name ?: 'Untitled page',
                    'today' => 0.0,
                    'yesterday' => 0.0,
                ];

                $date = $rec->date instanceof Carbon ? $rec->date->toDateString() : (string) $rec->date;
                if ($date === $today) {
                    $pages[$key]['today'] += (float) $rec->budget;
                } elseif ($date === $yesterday) {
                    $pages[$key]['yesterday'] += (float) $rec->budget;
                }
            }

            $totalToday = 0.0;
            $totalYesterday = 0.0;

            foreach (collect($pages)->sortByDesc('today') as $page) {
                $diff = $page['today'] - $page['yesterday'];
                $totalToday += $page['today'];
                $totalYesterday += $page['yesterday'];

                $budget = number_format($page['today'], 2);
                $lines[] = "• {$page['name']} — {$budget}  ({$this->formatDelta($diff)})";
            }

            $total = number_format($totalToday, 2);
            $totalDiff = $this->formatDelta($totalToday - $totalYesterday);
            $lines[] = "_Total: {$total}  ({$totalDiff} vs yesterday)_";
            $lines[] = '';
        }

        $body = trim(implode("\n", $lines));

        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }

    /**
     * Format a budget delta with a direction arrow, e.g. "🔺 +120.00".
     */
    private function formatDelta(float $diff): string
    {
        if (abs($diff) < 0.005) {
            return '➖ 0.00';
        }

        $arrow = $diff > 0 ? '🔺' : '🔻';
        $sign = $diff > 0 ? '+' : '−';

        return $arrow.' '.$sign.number_format(abs($diff), 2);
    }
}

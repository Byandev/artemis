<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\PageDailyBudgetRecord;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\MetaAds\Console\Commands\Concerns\FormatsBudgetTable;

class ReportPageBudgetsToDiscordCommand extends Command
{
    use FormatsBudgetTable;

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
     * Build the Discord embed body: one monospace table per workspace showing
     * each page's budget today and the difference vs yesterday, with a total
     * row — mirroring the Ad Spent Tracker. Wrapped in code fences so Discord
     * renders it as an aligned table. Capped to Discord's 4096-char limit.
     *
     * @param  Collection<int, PageDailyBudgetRecord>  $records
     */
    private function buildDescription($records, string $today, string $yesterday): string
    {
        if ($records->isEmpty()) {
            return "No page budgets were recorded for {$today}.";
        }

        $blocks = [];

        foreach ($records->groupBy('workspace_id') as $group) {
            $workspaceName = $group->first()->workspace?->name ?? 'Unknown workspace';

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

            $rows = [];
            $totalToday = 0.0;
            $totalYesterday = 0.0;

            foreach (collect($pages)->sortByDesc('today') as $page) {
                $totalToday += $page['today'];
                $totalYesterday += $page['yesterday'];

                $rows[] = [
                    'name' => $page['name'],
                    'today' => number_format($page['today'], 2),
                    'diff' => $this->formatDelta($page['today'] - $page['yesterday']),
                ];
            }

            $totalRow = [
                'name' => 'Total',
                'today' => number_format($totalToday, 2),
                'diff' => $this->formatDelta($totalToday - $totalYesterday),
            ];

            $blocks[] = "**{$workspaceName}**\n```\n".$this->renderTable($rows, $totalRow, 'Page')."\n```";
        }

        $body = trim(implode("\n", $blocks));

        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }
}

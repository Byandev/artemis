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

            $blocks[] = "**{$workspaceName}**\n```\n".$this->renderTable($rows, $totalRow)."\n```";
        }

        $body = trim(implode("\n", $blocks));

        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }

    /**
     * Render an aligned fixed-width table: Page | Today | Diff, plus a total row.
     *
     * @param  array<int, array{name: string, today: string, diff: string}>  $rows
     * @param  array{name: string, today: string, diff: string}  $totalRow
     */
    private function renderTable(array $rows, array $totalRow): string
    {
        $maxName = 22;
        $all = array_merge($rows, [$totalRow]);

        $nameW = mb_strlen('Page');
        $todayW = mb_strlen('Today');
        $diffW = mb_strlen('Diff');

        foreach ($all as $r) {
            $nameW = max($nameW, mb_strlen($this->truncate($r['name'], $maxName)));
            $todayW = max($todayW, mb_strlen($r['today']));
            $diffW = max($diffW, mb_strlen($r['diff']));
        }

        $line = fn (string $name, string $today, string $diff): string => $this->pad($this->truncate($name, $maxName), $nameW, false)
            .'  '.$this->pad($today, $todayW, true)
            .'  '.$this->pad($diff, $diffW, true);

        $sep = str_repeat('-', $nameW + $todayW + $diffW + 4);

        $out = [$line('Page', 'Today', 'Diff'), $sep];
        foreach ($rows as $r) {
            $out[] = $line($r['name'], $r['today'], $r['diff']);
        }
        $out[] = $sep;
        $out[] = $line($totalRow['name'], $totalRow['today'], $totalRow['diff']);

        return implode("\n", $out);
    }

    /**
     * Format a budget delta as a signed amount, e.g. "+120.00" / "-50.00".
     * Plain ASCII so columns stay aligned inside the monospace code block.
     */
    private function formatDelta(float $diff): string
    {
        if (abs($diff) < 0.005) {
            return '0.00';
        }

        return ($diff > 0 ? '+' : '-').number_format(abs($diff), 2);
    }

    /**
     * Pad a string to the given display width (multibyte-safe), left or right.
     */
    private function pad(string $value, int $width, bool $alignRight): string
    {
        $gap = $width - mb_strlen($value);
        if ($gap <= 0) {
            return $value;
        }

        $padding = str_repeat(' ', $gap);

        return $alignRight ? $padding.$value : $value.$padding;
    }

    /**
     * Truncate an over-long name with an ellipsis, keeping a fixed display width.
     */
    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1).'…' : $value;
    }
}

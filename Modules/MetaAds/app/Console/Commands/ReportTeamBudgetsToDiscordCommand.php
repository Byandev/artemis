<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\Team;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\MetaAds\Console\Commands\Concerns\FormatsBudgetTable;

class ReportTeamBudgetsToDiscordCommand extends Command
{
    use FormatsBudgetTable;

    protected $signature = 'metaads:report-team-budgets
        {--date= : Report date (YYYY-MM-DD, defaults to today).}';

    protected $description = "Send each team its own ad-spend budget summary to the team's Discord webhook.";

    public function handle(DiscordNotifier $discord): int
    {
        $today = $this->option('date') ?: Carbon::today()->toDateString();
        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        $teams = Team::query()
            ->whereNotNull('discord_webhook_url')
            ->where('discord_webhook_url', '!=', '')
            ->with(['shops.pages:id,shop_id,name'])
            ->get();

        if ($teams->isEmpty()) {
            $this->info('No teams have a Discord webhook configured — nothing to send.');

            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($teams as $team) {
            // Team ownership now flows through shops (team_shop), so a team's pages
            // are the pages of every shop assigned to it.
            $pages = $team->shops->flatMap->pages;
            $pageIds = $pages->pluck('id');

            $records = $pageIds->isEmpty()
                ? collect()
                : PageDailyBudgetRecord::query()
                    ->whereIn('page_id', $pageIds)
                    ->whereIn('date', [$today, $yesterday])
                    ->get(['page_id', 'date', 'budget']);

            $sent = $discord->send("📊 {$team->name} — daily ad budget", [
                'title' => "{$team->name} budgets — {$today}",
                'description' => $this->buildDescription($team, $pages, $records, $today, $yesterday),
                'color' => 0xFAA61A,
                'footer' => ['text' => 'metaads:report-team-budgets'],
            ], $team->discord_webhook_url);

            if ($sent) {
                $sentCount++;
            } else {
                $this->warn("Failed to send budget report for team {$team->name} — check its webhook URL and the logs.");
            }
        }

        $this->info("Sent team budget reports for {$today} ({$sentCount}/{$teams->count()} team(s)).");

        return self::SUCCESS;
    }

    /**
     * Per-page budget table for a single team: each of the team's pages (across
     * all of its shops) with its budget today and the delta vs yesterday, plus a
     * total row, wrapped in a Discord code fence so columns align. Capped to
     * Discord's 4096-char limit.
     *
     * @param  Collection<int, Page>  $teamPages
     * @param  Collection<int, PageDailyBudgetRecord>  $records
     */
    private function buildDescription(Team $team, $teamPages, $records, string $today, string $yesterday): string
    {
        if ($teamPages->isEmpty()) {
            return "No pages are assigned to **{$team->name}** yet.";
        }

        // Seed every team page at 0 so pages with no record still appear.
        $pages = [];
        foreach ($teamPages as $page) {
            $pages[$page->id] = [
                'name' => $page->name ?: 'Untitled page',
                'today' => 0.0,
                'yesterday' => 0.0,
            ];
        }

        foreach ($records as $rec) {
            if (! isset($pages[$rec->page_id])) {
                continue;
            }

            $date = $rec->date instanceof Carbon ? $rec->date->toDateString() : (string) $rec->date;
            if ($date === $today) {
                $pages[$rec->page_id]['today'] += (float) $rec->budget;
            } elseif ($date === $yesterday) {
                $pages[$rec->page_id]['yesterday'] += (float) $rec->budget;
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

        $body = "```\n".$this->renderTable($rows, $totalRow, 'Page')."\n```";

        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }
}

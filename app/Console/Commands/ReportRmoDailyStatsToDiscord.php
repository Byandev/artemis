<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\DiscordNotifier;
use App\Support\RmoDailyStats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Posts the RMO day in numbers to each opted-in workspace's Discord webhook.
 *
 * Scheduled hourly and self-selecting, the same shape as the inventory reports:
 * every workspace whose configured send time matches the current hour gets its
 * report, everyone else is skipped. That keeps the send time a per-workspace
 * setting rather than something that has to be redeployed.
 */
class ReportRmoDailyStatsToDiscord extends Command
{
    protected $signature = 'rmo:report-daily-stats
        {--date= : Day to report (YYYY-MM-DD, defaults to today).}
        {--force : Send now, ignoring each workspace\'s configured send time.}
        {--workspace= : Limit to one workspace id or slug.}';

    protected $description = "Post a Discord summary of the day's RMO delivery and call-log statistics, per workspace.";

    public function handle(DiscordNotifier $discord): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $force = (bool) $this->option('force');
        $nowHHMM = now()->format('H:i');

        $workspaces = $this->workspacesDueNow($force, $nowHHMM);

        if ($workspaces->isEmpty()) {
            $this->info($force
                ? 'No workspace has the RMO daily report enabled with a webhook to post to.'
                : "No workspace is due an RMO daily report at {$nowHHMM}.");

            return self::SUCCESS;
        }

        $prettyDate = Carbon::parse($date)->format('F j, Y');
        $sentCount = 0;

        foreach ($workspaces as $workspace) {
            $stats = RmoDailyStats::for($workspace, $date);

            $sent = $discord->send('', [
                'title' => "RMO Daily Report — {$prettyDate}",
                'description' => $this->body($stats),
                'color' => 0x10B981,
                'footer' => ['text' => $workspace->name.' • all users'],
            ], $workspace->rmoSetting->discord_webhook_url);

            if ($sent) {
                $sentCount++;
                $this->line("Sent RMO report for {$workspace->name}.");
            } else {
                $this->warn("Failed to send RMO report for workspace {$workspace->name} — check the webhook URL and logs.");
            }
        }

        $this->info("RMO daily report for {$date}: sent to {$sentCount} workspace(s).");

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
        $only = $this->option('workspace');

        return Workspace::query()
            ->whereHas('rmoSetting', function ($query) use ($force, $nowHHMM) {
                $query->where('discord_daily_stats_enabled', true)
                    ->whereNotNull('discord_webhook_url')
                    ->where('discord_webhook_url', '!=', '')
                    ->unless($force, fn ($q) => $q->where('discord_send_at', $nowHHMM));
            })
            ->when($only, fn ($query) => $query->where(function ($q) use ($only) {
                $q->where('id', $only)->orWhere('slug', $only);
            }))
            ->with('rmoSetting:id,workspace_id,discord_webhook_url')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * The day as two short lists: parcels, then calls.
     *
     * Discord's inline embed fields lay out three to a row and wrap wherever
     * they run out of width, which scatters ten related numbers across an
     * uneven grid. A fenced block is monospaced, so labels and values line up
     * in a column and the whole thing reads top to bottom.
     *
     * @param  array<string, int|float|null>  $stats
     */
    private function body(array $stats): string
    {
        // An em dash rather than 0% / 0s: with no calls yet there is no rate to
        // report, and a zero would read as a bad day rather than a quiet one.
        $avg = $stats['avg_call_duration'] === null
            ? '—'
            : RmoDailyStats::formatDuration((int) round($stats['avg_call_duration']));

        $hitRate = $stats['hit_rate'] === null
            ? '—'
            : number_format($stats['hit_rate'], 1).'%';

        $deliveries = $this->rows([
            'For delivery today' => number_format($stats['total_for_delivery']),
            'Called' => number_format($stats['called']),
            'Delivered' => number_format($stats['delivered']),
            'Returning' => number_format($stats['returning']),
            'Problematic' => number_format($stats['problematic']),
        ]);

        $calls = $this->rows([
            'RMO calls' => number_format($stats['total_call_logs']),
            'Total duration' => RmoDailyStats::formatDuration($stats['total_call_duration']),
            'Connected (3s+)' => number_format($stats['connected_call_logs']),
            'Avg duration' => $avg,
            'Hit rate' => $hitRate,
        ]);

        return "**📦 Deliveries**\n{$deliveries}\n**📞 Calls**\n{$calls}";
    }

    /**
     * A fenced block of `label   value` rows, values flush right so the eye can
     * run down the column. Widths come from the content, so nothing is clipped
     * and nothing is padded further than it needs to be.
     *
     * @param  array<string, string>  $rows
     */
    private function rows(array $rows): string
    {
        $labelWidth = max(array_map('strlen', array_keys($rows)));
        $valueWidth = max(array_map('strlen', array_values($rows)));

        $lines = [];

        foreach ($rows as $label => $value) {
            $lines[] = str_pad($label, $labelWidth).'   '.str_pad($value, $valueWidth, ' ', STR_PAD_LEFT);
        }

        return "```\n".implode("\n", $lines)."\n```";
    }
}

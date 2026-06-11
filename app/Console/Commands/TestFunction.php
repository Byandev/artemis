<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Services\MetaGraphClient;

class TestFunction extends Command
{
    protected $signature = 'test-function {--account=24070090862593400} {--scenario=}';

    protected $description = 'Diagnostic harness for the Meta Insights API. Sweeps several parameter combos and reports timing.';

    public function handle(): int
    {
        $accountId = (string) $this->option('account');

        $account = AdAccount::find($accountId);

        if (! $account) {
            $this->error("AdAccount {$accountId} not found locally. Run metaads:sync-ad-accounts first.");

            return self::FAILURE;
        }

        $metaUser = $account->metaUsers()->first();

        if (! $metaUser) {
            $this->error("No MetaUser linked to AdAccount {$accountId}.");

            return self::FAILURE;
        }

        $this->line("AdAccount: {$account->id} ({$account->name})");
        $this->line("MetaUser:  {$metaUser->id} ({$metaUser->name})");
        $this->newLine();

        $client = $metaUser->graphClient();
        $client->timeoutSeconds = 180;

        $minimalFields = 'ad_id,date_start,impressions,spend';

        $allFields = implode(',', [
            'ad_id', 'adset_id', 'campaign_id', 'date_start', 'date_stop',
            'impressions', 'reach', 'clicks', 'unique_clicks',
            'inline_link_clicks', 'unique_inline_link_clicks',
            'inline_post_engagement', 'full_view_impressions', 'full_view_reach',
            'estimated_ad_recallers',
            'instant_experience_clicks_to_open', 'instant_experience_clicks_to_start',
            'spend', 'social_spend',
            'auction_bid', 'auction_max_competitor_bid',
            'canvas_avg_view_time',
            'quality_ranking', 'engagement_rate_ranking', 'conversion_rate_ranking',
            'attribution_setting', 'objective', 'buying_type', 'optimization_goal', 'account_currency',
            'actions', 'action_values', 'unique_actions', 'conversions', 'conversion_values',
            'outbound_clicks', 'unique_outbound_clicks',
            'catalog_segment_actions', 'catalog_segment_value',
            'video_play_actions', 'video_p25_watched_actions', 'video_p50_watched_actions',
            'video_p75_watched_actions', 'video_p95_watched_actions', 'video_p100_watched_actions',
            'video_thruplay_watched_actions', 'video_avg_time_watched_actions',
            'video_30_sec_watched_actions', 'video_continuous_2_sec_watched_actions',
            'video_time_watched_actions', 'video_play_curve_actions',
        ]);

        $today = Carbon::today();

        $scenarios = [
            ['label' => '1d, minimal fields, limit=25', 'days' => 1, 'fields' => $minimalFields, 'limit' => 25],
            ['label' => '1d, all fields, limit=25', 'days' => 1, 'fields' => $allFields, 'limit' => 25],
            ['label' => '7d, minimal fields, limit=25', 'days' => 7, 'fields' => $minimalFields, 'limit' => 25],
            ['label' => '7d, all fields, limit=25', 'days' => 7, 'fields' => $allFields, 'limit' => 25],
            ['label' => '30d, minimal fields, limit=25', 'days' => 30, 'fields' => $minimalFields, 'limit' => 25],
            ['label' => '30d, all fields, limit=25', 'days' => 30, 'fields' => $allFields, 'limit' => 25],
            ['label' => '30d, all fields, limit=10', 'days' => 30, 'fields' => $allFields, 'limit' => 10],
            ['label' => '90d, all fields, limit=10', 'days' => 90, 'fields' => $allFields, 'limit' => 10],
            ['label' => '90d, all fields, limit=25 (failing config)', 'days' => 90, 'fields' => $allFields, 'limit' => 25],
        ];

        $only = $this->option('scenario');

        $rows = [];

        foreach ($scenarios as $scenario) {
            if ($only !== null && ! str_contains($scenario['label'], $only)) {
                continue;
            }

            $params = [
                'level' => 'ad',
                'time_increment' => 1,
                'time_range' => json_encode([
                    'since' => $today->copy()->subDays($scenario['days'])->toDateString(),
                    'until' => $today->toDateString(),
                ]),
                'fields' => $scenario['fields'],
                'limit' => $scenario['limit'],
            ];

            $this->line("→ {$scenario['label']}");

            $start = microtime(true);
            $rowCount = 0;
            $error = null;

            try {
                foreach ($this->iterateOnePage($client, "{$account->graphAccountId()}/insights", $params) as $row) {
                    $rowCount++;
                    if ($rowCount === 1) {
                        $this->line('  first row keys: '.implode(', ', array_keys($row)));
                    }
                }
            } catch (MetaGraphException $e) {
                $error = "Meta #{$e->errorCode}/{$e->errorSubcode}: {$e->getMessage()}";
            } catch (\Throwable $e) {
                $error = get_class($e).': '.$e->getMessage();
            }

            $elapsed = round((microtime(true) - $start) * 1000);

            $status = $error ? '<fg=red>FAIL</>' : '<fg=green>OK</>';
            $detail = $error ?? "{$rowCount} rows on first page";

            $this->line("  {$status} ({$elapsed} ms) — {$detail}");
            $this->newLine();

            $rows[] = [
                'scenario' => $scenario['label'],
                'elapsed_ms' => $elapsed,
                'status' => $error ? 'fail' : 'ok',
                'detail' => $error ?? "{$rowCount} rows",
            ];
        }

        $this->table(['Scenario', 'Elapsed (ms)', 'Status', 'Detail'], $rows);

        return self::SUCCESS;
    }

    /**
     * Fetch only the first page (no pagination) so we measure pure server-side
     * compute time for each scenario, not the full walk.
     */
    private function iterateOnePage(MetaGraphClient $client, string $path, array $params): \Generator
    {
        $body = $client->get($path, $params);

        foreach ($body['data'] ?? [] as $item) {
            yield $item;
        }
    }
}

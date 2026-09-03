<?php

namespace Database\Seeders;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\Intern;

/**
 * Sample advertiser data for the Sales & Marketing dashboard, for local work.
 *
 * The dashboard's Total Sales reads real orders, but ad spend, ROAS and the
 * leaders read advertiser_performance_daily_records — a table normally filled
 * by the sync jobs. Without it those cards sit at ₱0 or "—", so there is
 * nothing to look at while building the page.
 *
 * Spend is derived from each day's actual confirmed sales rather than invented
 * from nothing, so blended ROAS lands somewhere believable (~3) and moves the
 * way the real thing would. Delivery outcomes are split off the same sales so
 * the per-advertiser RTS the leaders rank on has something to read. Re-running
 * updates the same rows rather than stacking duplicates.
 *
 * Not wired into DatabaseSeeder — run it deliberately:
 *   php artisan db:seed --class=SalesMarketingDashboardSampleSeeder
 */
class SalesMarketingDashboardSampleSeeder extends Seeder
{
    /** How far back to generate, in days, ending yesterday. */
    private const DAYS = 120;

    /** Blended ROAS the generated spend aims at, before per-day noise. */
    private const TARGET_ROAS = 3.1;

    /** Spend on a day with no sales at all, so the series never flatlines. */
    private const IDLE_DAY_SPEND = 1500.0;

    /** Share of an advertiser's sales that comes back, before their own bias. */
    private const BASE_RTS_RATE = 0.2;

    public function run(): void
    {
        // Repeatable output: same numbers every run, so a screenshot taken today
        // still matches the dashboard tomorrow.
        mt_srand(20260831);

        $workspaces = Workspace::where('sales_marketing_dashboard_module_enabled', true)->get();

        if ($workspaces->isEmpty()) {
            $this->command?->warn('No workspace has the Sales & Marketing module enabled — nothing to seed.');

            return;
        }

        foreach ($workspaces as $workspace) {
            $this->seedWorkspace($workspace);
        }
    }

    private function seedWorkspace(Workspace $workspace): void
    {
        // The dashboard reads whichever source the workspace is on, so the rows
        // have to be written against the same one or they are invisible to it.
        $gencys = (bool) $workspace->is_gencys_partner;

        $advertisers = $gencys
            ? Intern::where('workspace_id', $workspace->id)
                ->get()
                ->map(fn ($intern) => ['id' => (int) $intern->id, 'name' => $intern->full_name])
            : $workspace->users()->get(['users.id', 'users.name'])
                ->map(fn ($user) => ['id' => (int) $user->id, 'name' => $user->name]);

        if ($advertisers->isEmpty()) {
            $this->command?->warn("Workspace {$workspace->slug}: no advertisers to attribute spend to — skipped.");

            return;
        }

        $end = Carbon::yesterday();
        $start = $end->copy()->subDays(self::DAYS - 1);

        $sales = $this->dailySales($workspace, $start, $end);
        $written = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $day = $sales[$key] ?? ['sales' => 0.0, 'orders' => 0];

            // Blended ROAS wobbles day to day around the target; spend is then
            // whatever that ROAS implies for the sales actually taken.
            $roas = self::TARGET_ROAS * $this->jitter(0.75, 1.35);
            $spend = $day['sales'] > 0 ? $day['sales'] / $roas : self::IDLE_DAY_SPEND;

            // Attributed sales run near the blended figure but not on top of it —
            // the gap between the two ratios is what the card is there to show.
            $attributedShare = $this->jitter(0.85, 1.2);

            $written += $this->writeDay(
                $workspace,
                $gencys,
                $advertisers,
                $key,
                $spend,
                $day['sales'] * $attributedShare,
                $day['orders'],
            );
        }

        $this->command?->info(
            "Workspace {$workspace->slug}: {$written} advertiser daily records "
            ."({$start->toDateString()} → {$end->toDateString()}, "
            .($gencys ? 'gencys' : 'artemis').' source).'
        );
    }

    /**
     * Split one day across the advertisers and upsert a row each.
     *
     * @param  Collection<int, array{id: int, name: ?string}>  $advertisers
     */
    private function writeDay(
        Workspace $workspace,
        bool $gencys,
        $advertisers,
        string $date,
        float $spend,
        float $attributedSales,
        int $orders,
    ): int {
        // A handful of advertisers are active on any given day, not all of them.
        $active = $advertisers->shuffle()->take(max(1, (int) ceil($advertisers->count() * $this->jitter(0.3, 0.8))));
        $weights = $active->map(fn () => $this->jitter(0.5, 1.5));
        $total = $weights->sum();

        $written = 0;

        foreach ($active->values() as $i => $advertiser) {
            $share = $weights[$i] / $total;
            $rowSpend = round($spend * $share, 2);
            $rowSales = round($attributedSales * $share, 2);
            $rowOrders = (int) round($orders * $share);

            // Some advertisers are reliably better at getting parcels delivered
            // than others, so the "lowest RTS" leader is a standing fact rather
            // than whoever got lucky on the day. Derived from the id so it
            // holds steady across days and across runs.
            $rtsRate = min(0.6, max(0.03, self::BASE_RTS_RATE * $this->bias($advertiser['id']) * $this->jitter(0.8, 1.2)));

            $returnedAmount = round($rowSales * $rtsRate, 2);
            $returnedOrders = (int) round($rowOrders * $rtsRate);

            AdvertiserPerformanceDailyRecord::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'source' => $gencys
                        ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
                        : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS,
                    'advertiser_model' => $gencys ? 'intern' : 'user',
                    'advertiser_id' => $advertiser['id'],
                    'date' => $date,
                ],
                [
                    'advertiser_name' => $advertiser['name'],
                    'ad_spent' => $rowSpend,
                    'sales' => $rowSales,
                    'orders' => $rowOrders,
                    'roas' => $rowSpend > 0 ? round($rowSales / $rowSpend, 2) : null,
                    // Every peso of sales either lands or comes back, so the two
                    // split the day rather than being invented separately.
                    'returned_amount' => $returnedAmount,
                    'delivered_amount' => round($rowSales - $returnedAmount, 2),
                    'returned' => $returnedOrders,
                    'delivered' => max(0, $rowOrders - $returnedOrders),
                    'rts_rate' => round($rtsRate, 4),
                ],
            );

            $written++;
        }

        return $written;
    }

    /**
     * Real confirmed sales and order counts per day, keyed Y-m-d. Matches the
     * `totalSales` metric's rules (confirmed_at, excluding the two cancelled /
     * returned statuses) so the generated ROAS reflects the sales the dashboard
     * will actually show.
     *
     * @return array<string, array{sales: float, orders: int}>
     */
    private function dailySales(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        return DB::table('pancake_orders')
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', [6, 7])
            ->whereBetween('confirmed_at', [$start->toDateString().' 00:00:00', $end->toDateString().' 23:59:59'])
            ->groupByRaw('DATE(confirmed_at)')
            ->selectRaw('DATE(confirmed_at) as day, SUM(final_amount) as sales, COUNT(*) as orders')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->day => ['sales' => (float) $row->sales, 'orders' => (int) $row->orders],
            ])
            ->all();
    }

    /**
     * A stable per-advertiser multiplier in roughly [0.4, 1.6], derived from
     * their id so the same advertiser behaves the same way every run.
     */
    private function bias(int $advertiserId): float
    {
        return 0.4 + (($advertiserId * 37) % 100) / 100 * 1.2;
    }

    /** A random multiplier in [$min, $max]. */
    private function jitter(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }
}

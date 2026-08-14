<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;

/**
 * Demo campaigns for the Meta Ads → Ads Calendar, spread across the current
 * month and attributed to real pages so the Page and Page Owner filters have
 * something to narrow.
 *
 * The calendar keys days off `meta_ads_campaigns.start_time` and derives the
 * page from `meta_ads_sets.meta_page_id` (a page's primary key IS the FB page
 * id), so both are what this seeds.
 *
 * Safe to re-run: every row it creates lives in a reserved id range that is
 * cleared first, so real synced campaigns are never touched.
 */
class AdsCalendarDemoSeeder extends Seeder
{
    /** Reserved id range for demo rows — far above any real Meta id in use. */
    private const CAMPAIGN_ID_BASE = 7_700_000_000;

    private const SET_ID_BASE = 7_800_000_000;

    private const RANGE = 1_000_000;

    public function run(): void
    {
        $workspace = Workspace::query()
            ->whereHas('pages', fn ($q) => $q->whereNotNull('owner_id'))
            ->orderBy('id')
            ->first();

        if (! $workspace) {
            $this->command?->warn('No workspace with owned pages — nothing seeded.');

            return;
        }

        $accountId = AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->value('meta_ads_accounts.id');

        if (! $accountId) {
            $this->command?->warn("No actively-synced ad account in {$workspace->name} — nothing seeded.");

            return;
        }

        // One page per owner keeps the calendar legend readable while still
        // covering every owner the filter can offer.
        $pages = Page::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('owner_id')
            ->with('owner:id,name')
            ->get(['id', 'name', 'owner_id'])
            ->unique('owner_id')
            ->values();

        if ($pages->isEmpty()) {
            $this->command?->warn("No owned pages in {$workspace->name} — nothing seeded.");

            return;
        }

        $removed = $this->clearPrevious();

        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        $campaignId = self::CAMPAIGN_ID_BASE;
        $setId = self::SET_ID_BASE;
        $made = 0;
        $unassigned = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            // Uneven on purpose — a calendar where every cell matches shows
            // nothing when you filter.
            $perDay = match ($day->day % 5) {
                0 => 0,
                1, 2 => 1,
                3 => 2,
                default => 3,
            };

            for ($i = 0; $i < $perDay; $i++) {
                // Rotate pages by day+index so each owner lands on a different
                // mix of dates rather than every owner appearing every day.
                $page = $pages[($day->day + $i) % $pages->count()];

                // Every 7th campaign has no ad-set page, feeding the calendar's
                // "Unassigned page" bucket.
                $isUnassigned = ($made % 7) === 6;

                $campaign = Campaign::create([
                    'id' => $campaignId++,
                    'meta_ads_account_id' => $accountId,
                    'name' => sprintf(
                        'Demo — %s %s #%d',
                        $isUnassigned ? 'Unassigned' : $page->owner?->name ?? 'Unknown',
                        $day->format('M j'),
                        $i + 1,
                    ),
                    'status' => 'ACTIVE',
                    'effective_status' => 'ACTIVE',
                    'start_time' => $day->copy()->setTime(9 + $i, 0),
                ]);

                AdSet::create([
                    'id' => $setId++,
                    'meta_ads_account_id' => $accountId,
                    'meta_ads_campaign_id' => $campaign->id,
                    'meta_page_id' => $isUnassigned ? null : $page->id,
                    'name' => 'Demo Ad Set '.$campaign->id,
                    'status' => 'ACTIVE',
                    'effective_status' => 'ACTIVE',
                ]);

                $made++;
                $isUnassigned && $unassigned++;
            }
        }

        $this->command?->info(sprintf(
            '%s: removed %d old demo campaign(s), created %d across %d page owner(s) for %s (%d unassigned).',
            $workspace->name,
            $removed,
            $made,
            $pages->count(),
            $start->format('F Y'),
            $unassigned,
        ));
    }

    /** Drop only rows this seeder owns, by reserved id range. */
    private function clearPrevious(): int
    {
        AdSet::whereBetween('id', [self::SET_ID_BASE, self::SET_ID_BASE + self::RANGE])->delete();

        return Campaign::whereBetween('id', [self::CAMPAIGN_ID_BASE, self::CAMPAIGN_ID_BASE + self::RANGE])->delete();
    }
}

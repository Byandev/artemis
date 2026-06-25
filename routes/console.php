<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire-trials')->dailyAt('00:05');
Schedule::command('trigger-fetch-page-orders')->hourly();
Schedule::command('inventory:sync-averages')->hourly();

// Fetch ERP data for inventory items with sales keywords. Jobs are queued with a
// staggered delay (the command's --delay default) so the n8n webhook isn't hit all
// at once. Runs three times a day: 9am, 12nn, 5pm.
Schedule::command('trigger-fetch-erp-inventory')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('trigger-fetch-erp-inventory')->dailyAt('12:00')->withoutOverlapping();
Schedule::command('trigger-fetch-erp-inventory')->dailyAt('17:00')->withoutOverlapping();
Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-users')->daily(7);

// Schedule::command('trigger-fetch-csr-erp-dail-records --date="2 days ago"')->dailyAt('02:00');
// Schedule::command('trigger-fetch-csr-erp-dail-records --date="3 days ago"')->dailyAt('03:00');
// Schedule::command('trigger-fetch-csr-erp-dail-records --date="4 days ago"')->dailyAt('04:00');
// Schedule::command('trigger-fetch-csr-erp-dail-records --date="5 days ago"')->dailyAt('05:00');
// Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('12:00');
// Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('15:00');

Schedule::command('sync:csr-daily-records')->dailyAt('03:00');
Schedule::command('sync:csr-rmo-daily-records')->dailyAt('04:00');

// ── MetaAds ─────────────────────────────────────────────────────────────
// Ad accounts rarely change; a light refresh every 30 min keeps new accounts
// and status changes visible.
// Schedule::command('metaads:sync-ad-accounts')->everyThirtyMinutes()->withoutOverlapping();

// Entity tree (campaigns → ad sets → ads → creatives) changes when advertisers
// edit Ads Manager — refresh every 6 hours.
Schedule::command('meta-ads:sync-campaigns')->everySixHours()->withoutOverlapping();
Schedule::command('meta-ads:sync-ad-sets')->everySixHours()->withoutOverlapping();
Schedule::command('meta-ads:sync-ads')->everySixHours()->withoutOverlapping();
Schedule::command('meta-ads:sync-creatives')->daily()->withoutOverlapping();

// Insights, narrowing the window as numbers settle:
//  • 00:30 daily — re-pull the last 3 days to absorb late-attributed conversions.
//  • every 6h    — refresh yesterday (attribution still landing).
//  • every 2h    — refresh today (the live row updates throughout the day).
Schedule::command('meta-ads:sync-insights --days=3')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('meta-ads:sync-insights --days=1 --until=yesterday')->everySixHours()->withoutOverlapping();
Schedule::command('meta-ads:sync-insights --days=1')->hourlyAt(30)->withoutOverlapping();

// Snapshot end-of-day budgets so we have history Meta doesn't keep. Runs at
// 23:55 server time, after the 23:30 entity sync has captured the day's
// final budget state.
Schedule::command('metaads:capture-budgets')->everyFourHours()->withoutOverlapping();

// Runs hourly; each optimization rule is evaluated only when its own
// user-configured schedule (frequency / run-at hour) is due.
Schedule::command('meta-ads:evaluate-optimization-rules')->hourly()->withoutOverlapping();

// Entity monitor: once a day (after the 00:30 insights re-pull settles), record
// each in-test entity's suggested status + reason as that day's history row.
Schedule::command('meta-ads:evaluate-entity-monitor')->dailyAt('01:00')->withoutOverlapping();

// Post the day's per-page ad budgets to Discord every morning (08:00 app tz).
Schedule::command('metaads:report-page-budgets')->dailyAt('08:00');

// Post the day's per-user (page owner) ad budgets to Discord every morning.
Schedule::command('metaads:report-user-budgets')->dailyAt('08:00');

// Post each team's own ad budgets to its team Discord webhook every morning.
Schedule::command('metaads:report-team-budgets')->dailyAt('08:00');

// Schedule::command('analytics:rollup --date=today')->hourly()->withoutOverlapping();
// Schedule::command('analytics:rollup --date=yesterday')->dailyAt('01:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="2 days ago"')->dailyAt('02:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="3 days ago"')->dailyAt('03:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="4 days ago"')->dailyAt('04:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="5 days ago"')->dailyAt('05:00')->withoutOverlapping();

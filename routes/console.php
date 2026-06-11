<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire-trials')->dailyAt('00:05');
Schedule::command('trigger-fetch-page-orders')->hourly();
Schedule::command('inventory:sync-averages')->hourly();
Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-users')->daily(7);

//Schedule::command('trigger-fetch-csr-erp-dail-records --date="2 days ago"')->dailyAt('02:00');
//Schedule::command('trigger-fetch-csr-erp-dail-records --date="3 days ago"')->dailyAt('03:00');
//Schedule::command('trigger-fetch-csr-erp-dail-records --date="4 days ago"')->dailyAt('04:00');
//Schedule::command('trigger-fetch-csr-erp-dail-records --date="5 days ago"')->dailyAt('05:00');
//Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('12:00');
//Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('15:00');

Schedule::command('sync:csr-daily-records')->dailyAt('03:00');
Schedule::command('sync:csr-rmo-daily-records')->dailyAt('04:00');

// ── MetaAds ─────────────────────────────────────────────────────────────
// Entity metadata changes whenever an advertiser edits something in Ads
// Manager. Refresh every 30 min so dashboards don't lag too far behind.
Schedule::command('metaads:sync-ad-accounts')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('metaads:sync-campaigns')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('metaads:sync-ad-sets')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('metaads:sync-ads')->everyThirtyMinutes()->withoutOverlapping();

// Creatives change rarely; daily is enough.
Schedule::command('metaads:sync-creatives')->dailyAt('02:30')->withoutOverlapping();

// Insights: today's row updates throughout the day; refresh every 15 min.
Schedule::command('metaads:sync-insights --days=1')->everyFifteenMinutes()->withoutOverlapping();
// Hourly catch-up for the last 7 days to absorb late-attributed conversions.
Schedule::command('metaads:sync-insights --days=7')->hourly()->withoutOverlapping();

// Snapshot end-of-day budgets so we have history Meta doesn't keep. Runs at
// 23:55 server time, after the 23:30 entity sync has captured the day's
// final budget state.
Schedule::command('metaads:capture-budgets')->dailyAt('23:55')->withoutOverlapping();

// Evaluate optimization rules once a day and report which campaigns/ad sets
// would be affected. Currently a dry run — it does not apply any changes.
Schedule::command('meta-ads:evaluate-optimization-rules')->dailyAt('03:00')->withoutOverlapping();

// Schedule::command('analytics:rollup --date=today')->hourly()->withoutOverlapping();
// Schedule::command('analytics:rollup --date=yesterday')->dailyAt('01:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="2 days ago"')->dailyAt('02:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="3 days ago"')->dailyAt('03:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="4 days ago"')->dailyAt('04:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="5 days ago"')->dailyAt('05:00')->withoutOverlapping();

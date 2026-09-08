<?php

Schedule::command('subscriptions:expire-trials')->dailyAt('00:05');

// Warn workspaces 5 days, 3 days, and on the day their subscription falls due.
// Runs after expire-trials so a subscription that lapsed overnight isn't still
// being reminded about as if it were live.
Schedule::command('subscriptions:send-due-reminders')->dailyAt('08:00')->withoutOverlapping();

// Build Artemis-source advertiser performance (Pancake POS + Meta Ads) daily,
// rebuilding a trailing 3-day window to absorb late Meta attribution.
Schedule::command('build-advertiser-daily-performance --days=3')->dailyAt('01:00')->withoutOverlapping();
// Same Pancake/Meta data, aggregated per page instead of per advertiser.
Schedule::command('build-page-daily-performance --days=3')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('trigger-fetch-shop-orders')->hourly();
Schedule::command('inventory:sync-averages')->hourly();

Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-users')->daily(7);

// Gencys ERP
Schedule::command('gencys-erp:sweep-sync-batches')->everyMinute()->withoutOverlapping();
Schedule::command('gencys-erp:expire-stale-sync-runs')->hourly();

Schedule::command('gencys-erp:sync')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync')->dailyAt('12:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync')->dailyAt('14:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync')->dailyAt('17:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync')->dailyAt('19:00')->withoutOverlapping();

Schedule::command('inventory:snapshot-items')->dailyAt('10:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('13:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('13:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('18:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('20:30')->withoutOverlapping();

// Intern daily records still fan out on the old fixed-timer path.
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('09:30')->withoutOverlapping();
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('13:30')->withoutOverlapping();
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('16:30')->withoutOverlapping();

Schedule::command('sync:csr-daily-records')->dailyAt('03:00');
Schedule::command('sync:csr-daily-call-records')->dailyAt('04:15');

Schedule::command('sync:shop-rts-snapshot')->dailyAt('02:30')->withoutOverlapping();

// Pull RMO statuses in line with the courier's parcel status for workspaces
// that opted in. Runs once at midnight, which lands on the default two-day
// window (today + yesterday) just as the day rolls over — so the day that has
// only just ended gets closed out, including parcels whose final status the
// courier reported late in the evening.
Schedule::command('rmo:apply-auto-tag')->dailyAt('00:00')->withoutOverlapping();

// ── RMO (Discord) ───────────────────────────────────────────────────────
// Checked hourly; posts only for workspaces whose configured send time matches
// the current hour. Send times are whole hours only, so an hourly run always
// lands on the match.
Schedule::command('rmo:report-daily-stats')->hourly()->withoutOverlapping();

// ── Inventory (Discord) ─────────────────────────────────────────────────
// Checked hourly (top of each hour); each command posts only for workspaces
// whose configured send time matches the current hour. Send times are
// whole hours only (e.g. 08:00), so an hourly run always lands on the match.
Schedule::command('inventory:report-deliveries')->hourly()->withoutOverlapping();
Schedule::command('inventory:report-late-deliveries')->hourly()->withoutOverlapping();

// ── MetaAds ─────────────────────────────────────────────────────────────
// Ad accounts rarely change, but a new account (or a status flip to disabled)
// should not wait a day to show up. One Graph call per connected MetaUser, so
// hourly is cheap. Runs at the top of the hour, ahead of the :30 insights sync,
// so an account discovered here is already in the table when insights run.
Schedule::command('metaads:sync-ad-accounts')->hourly()->withoutOverlapping();

// Ad accounts Meta no longer reports as Active (disabled, unsettled, closed).
// Checked hourly; posts only for workspaces whose configured send time matches
// the current hour. Send times are whole hours only, so an hourly run always
// lands on the match. Runs at :05, after the sync above has refreshed statuses.
Schedule::command('metaads:report-inactive-accounts')->hourlyAt(5)->withoutOverlapping();

// Who has access to each ad account (Business Manager People list). Access
// changes are rare and the call is one request per account — daily is plenty.
Schedule::command('metaads:sync-ad-account-people')->dailyAt('02:00')->withoutOverlapping();

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
Schedule::command('meta-ads:sync-insights --days=1 --until=yesterday')->hourlyAt(30)->withoutOverlapping();
Schedule::command('meta-ads:sync-insights --days=1')->hourlyAt(30)->withoutOverlapping();

// Snapshot end-of-day budgets so we have history Meta doesn't keep. Runs at
// 23:55 server time, after the 23:30 entity sync has captured the day's
// final budget state.
Schedule::command('metaads:capture-budgets')->everyFourHours()->withoutOverlapping();

// Runs hourly; each optimization rule is evaluated only when its own
// user-configured schedule (frequency / run-at hour) is due.
Schedule::command('meta-ads:evaluate-optimization-rules')->hourly()->withoutOverlapping();

// Post the day's per-page ad budgets to Discord every morning (08:00 app tz).
Schedule::command('metaads:report-page-budgets')->dailyAt('08:00');

// Post the day's per-user (page owner) ad budgets to Discord every morning.
Schedule::command('metaads:report-user-budgets')->dailyAt('08:00');

// Post each team's own ad budgets to its team Discord webhook every morning.
Schedule::command('metaads:report-team-budgets')->dailyAt('08:00');

// Lesson videos are uploaded straight to S3 and only attached afterwards; an
// upload that is never attached leaves an orphan in the bucket that nothing
// else cleans up. 24h is well clear of any upload still in flight.
Schedule::command('courses:prune-pending-uploads')->dailyAt('04:00')->withoutOverlapping();

// Schedule::command('analytics:rollup --date=today')->hourly()->withoutOverlapping();
// Schedule::command('analytics:rollup --date=yesterday')->dailyAt('01:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="2 days ago"')->dailyAt('02:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="3 days ago"')->dailyAt('03:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="4 days ago"')->dailyAt('04:00')->withoutOverlapping();
// Schedule::command('analytics:rollup --date="5 days ago"')->dailyAt('05:00')->withoutOverlapping();

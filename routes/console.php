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
// Destination rollup behind the RTS heat map. A 7-day window, because an order's
// delivered/returning timestamp can land well after it was placed and a status
// correction moves it between days.
Schedule::command('build-order-location-daily-records --days=7')->dailyAt('01:30')->withoutOverlapping();
// Breakdown of each day's orders by the customer history they arrived with
// (the 'initial' phone-number report). A 7-day window, matching the location
// rollup above: orders are bucketed on confirmed_at and cancelled ones are
// dropped, so a settled day still changes when an order is cancelled days after
// it was confirmed. Fans out one job per (workspace, day) onto the analytics
// queue, so the scheduler returns immediately and the rebuild parallelises.
Schedule::command('build-page-order-report-breakdown-daily-records --days=7')->dailyAt('01:45')->withoutOverlapping();
Schedule::command('trigger-fetch-shop-orders')->hourly();
Schedule::command('inventory:sync-averages')->hourly();

Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-users')->daily(7);

// Gencys ERP
Schedule::command('gencys-erp:sweep-sync-batches')->everyMinute()->withoutOverlapping();
Schedule::command('gencys-erp:expire-stale-sync-runs')->hourly();

Schedule::command('gencys-erp:sync')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync --type=transaction_history,purchase_order,daily_sales_tracker')->dailyAt('12:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync --type=transaction_history,purchase_order,daily_sales_tracker')->dailyAt('14:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync --type=transaction_history,purchase_order,daily_sales_tracker')->dailyAt('17:00')->withoutOverlapping();
Schedule::command('gencys-erp:sync --type=transaction_history,purchase_order,daily_sales_tracker')->dailyAt('19:00')->withoutOverlapping();

Schedule::command('inventory:snapshot-items')->dailyAt('10:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('13:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('13:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('18:30')->withoutOverlapping();
Schedule::command('inventory:snapshot-items')->dailyAt('20:30')->withoutOverlapping();

// Intern daily records still fan out on the old fixed-timer path.
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('09:30')->withoutOverlapping();
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('13:30')->withoutOverlapping();
// Schedule::command('gencys-erp:trigger-fetch-intern-daily-records')->dailyAt('16:30')->withoutOverlapping();

// Welle ESC records, one job per connected user.
//
// 06:00 catches yesterday once it has settled. The command always writes the
// whole elapsed week rather than a single day, so each run also re-states the
// days before it — which is free, and corrects anything logged late.
//
// Caveat worth knowing: Welle's progress endpoint only ever answers for the
// week containing today. On a Monday the week has already rolled over, so the
// Sunday just gone is not in the response and this run cannot capture it.
Schedule::command('welle:fetch-daily-records')->dailyAt('06:00')->withoutOverlapping();

Schedule::command('sync:csr-daily-records')->everyTwoHours('03:00');
Schedule::command('sync:csr-daily-call-records')->everyTwoHours('04:15');

// Roll each shop's previous-14-days RTS rate onto shops.rts_snapshot so the RMO
// table can show and sort by it without aggregating pancake_orders per request.
// The 04:30 run follows the CSR rollups, once the previous day's orders have
// settled; the 13:00 one picks up statuses the courier reported during the
// morning. Both cover the same window of completed days, so the second run only
// ever corrects the first — it never shifts the window mid-day.
Schedule::command('sync:shop-rts-snapshot')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('sync:shop-rts-snapshot')->dailyAt('13:00')->withoutOverlapping();

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
// Ad accounts rarely change; a light refresh every 30 min keeps new accounts
// and status changes visible.
// Schedule::command('metaads:sync-ad-accounts')->everyThirtyMinutes()->withoutOverlapping();

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

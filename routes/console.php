<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trigger-fetch-page-orders')->everyThirtyMinutes();
Schedule::command('inventory:sync-averages')->hourly();
// Schedule::command('trigger-fetch-ads-data')->hourlyAt(30);
Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-customers')->hourlyAt(30);
Schedule::command('trigger-fetch-shops-users')->daily(7);

Schedule::command('trigger-fetch-csr-erp-dail-records --date="2 days ago"')->dailyAt('02:00');
Schedule::command('trigger-fetch-csr-erp-dail-records --date="3 days ago"')->dailyAt('03:00');
Schedule::command('trigger-fetch-csr-erp-dail-records --date="4 days ago"')->dailyAt('04:00');
Schedule::command('trigger-fetch-csr-erp-dail-records --date="5 days ago"')->dailyAt('05:00');
Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('12:00');
Schedule::command('trigger-fetch-csr-erp-dail-records')->dailyAt('15:00');

Schedule::command('sync:csr-daily-records')->dailyAt('03:00');

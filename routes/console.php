<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trigger-fetch-page-orders')->hourlyAt(0);
// Schedule::command('trigger-fetch-ads-data')->hourlyAt(30);
Schedule::command('save-parcel-journey-notification-log')->monthlyOn(14);
Schedule::command('trigger-fetch-shops-customers')->hourlyAt(30);
Schedule::command('trigger-fetch-shops-users')->daily(7);

Schedule::command('trigger-fetch-csr-erp-dail-records')->twiceDaily(10, 22);

Schedule::command('sync:csr-daily-records')->dailyAt('03:00');

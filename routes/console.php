<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trigger-fetch-page-orders')->hourlyAt(0);
Schedule::command('trigger-fetch-sequences')->weekly(1);
Schedule::command('trigger-fetch-sequence-statistics')->weekly(1);

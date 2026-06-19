<?php

namespace App\Enums\Logging;

/**
 * High-level grouping used to filter and route logs. Kept open-ended on
 * purpose — add cases as new domains need first-class monitoring.
 */
enum LogCategory: string
{
    case Auth = 'auth';                 // login, logout, password, 2FA
    case Data = 'data';                 // create / update / delete of domain records
    case Security = 'security';         // permission changes, suspicious activity, API keys
    case ScheduledJob = 'scheduled_job'; // cron / queued background work
    case Integration = 'integration';   // external services: Facebook, Pancake, ERP, n8n
    case System = 'system';             // misc framework / infra events
}

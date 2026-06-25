<?php

namespace App\Enums\Logging;

/**
 * How a system log entry was initiated. Only relevant for `LogType::System`.
 */
enum TriggerType: string
{
    case Scheduled = 'scheduled'; // fired by the scheduler (has a cron expression)
    case Event = 'event';         // fired by an application/domain event
    case Manual = 'manual';       // run by hand (artisan command, admin action)
}

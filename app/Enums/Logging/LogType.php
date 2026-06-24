<?php

namespace App\Enums\Logging;

/**
 * Distinguishes a user-initiated activity log from an automated system log.
 */
enum LogType: string
{
    case User = 'user';
    case System = 'system';
}

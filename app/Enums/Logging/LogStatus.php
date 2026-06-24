<?php

namespace App\Enums\Logging;

/**
 * Outcome of the logged action. `Failure` is the primary signal monitoring
 * and alerting key off.
 */
enum LogStatus: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Warning = 'warning';
    case Info = 'info';
}

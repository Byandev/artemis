<?php

namespace App\Facades;

use App\Services\Logging\ActivityLogger;
use App\Services\Logging\PendingActivityLog;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingActivityLog build()
 * @method static \App\Models\ActivityLog|null userActivity(\App\Enums\Logging\LogCategory $category, string $action, \App\Enums\Logging\LogStatus $status = \App\Enums\Logging\LogStatus::Success, ?string $message = null, array $metadata = [])
 * @method static \App\Models\ActivityLog|null systemEvent(\App\Enums\Logging\LogCategory $category, string $action, array $options = [])
 * @method static \App\Models\ActivityLog|null failure(\App\Enums\Logging\LogCategory $category, string $action, \Throwable|string|null $error = null, ?string $message = null, array $metadata = [])
 *
 * @see ActivityLogger
 */
class Activity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogger::class;
    }
}

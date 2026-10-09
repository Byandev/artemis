<?php

namespace Modules\TaskManagement\Enums;

enum TaskStatusType: string
{
    case NotStarted = 'not_started';
    case Active = 'active';
    case Done = 'done';
    case Closed = 'closed';

    /**
     * Determine whether a task in this status counts as finished.
     */
    public function isComplete(): bool
    {
        return in_array($this, [self::Done, self::Closed], true);
    }

    /**
     * Get every status type value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

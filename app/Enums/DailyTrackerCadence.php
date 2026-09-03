<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * How often a Daily Tracker deliverable has to be ticked.
 *
 * A completion is stored against the first day of the period it satisfies, so a
 * weekly item ticked on Wednesday still reads as done on Friday, while a daily
 * one has to be ticked again tomorrow.
 */
enum DailyTrackerCadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * The date a completion for $date is filed under.
     */
    public function periodStart(Carbon|CarbonImmutable $date): CarbonImmutable
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return match ($this) {
            self::Daily => $day,
            self::Weekly => $day->startOfWeek(),
        };
    }

    /**
     * Short label shown next to a deliverable when it is not a daily one.
     */
    public function badge(): ?string
    {
        return match ($this) {
            self::Daily => null,
            self::Weekly => 'WEEKLY',
        };
    }
}

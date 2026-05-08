<?php

namespace App\Metrics;

/**
 * Allowed values for the analytics $source toggle. Defined on a class rather than
 * the HasMetricSource trait because direct access to trait static members is
 * deprecated in modern PHP.
 */
final class MetricSource
{
    public const ROLLUP = 'rollup';

    public const LIVE = 'live';

    public static function normalize(?string $value): string
    {
        return $value === self::ROLLUP ? self::ROLLUP : self::LIVE;
    }
}

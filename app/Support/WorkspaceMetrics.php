<?php

namespace App\Support;

use App\Metrics\Orders\Aov;
use App\Metrics\Orders\AverageDeliveryDays;
use App\Metrics\Orders\AverageLifetimeValue;
use App\Metrics\Orders\RetentionRate;
use App\Metrics\Orders\RtsRate;
use App\Metrics\Orders\TimeToFirstOrder;
use App\Metrics\Orders\TotalOrders;
use App\Metrics\Orders\TotalSales;
use App\Models\Workspace;
use InvalidArgumentException;

final class WorkspaceMetrics
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * Registry: metricName => class
     * Add new metrics here (single line).
     */
    private const MAP = [
        'aov' => Aov::class,
        'rtsRate' => RtsRate::class,
        'totalSales' => TotalSales::class,
        'totalOrders' => TotalOrders::class,
        'retentionRate' => RetentionRate::class,
        'timeToFirstOrder' => TimeToFirstOrder::class,
        'avgLifetimeValue' => AverageLifetimeValue::class,
        'avgDeliveryDays' => AverageDeliveryDays::class,
    ];

    /**
     * @param  string[]  $names
     */
    public function extract(array $names): array
    {
        $out = [];
        $workspaceId = $this->workspace->id;

        foreach ($names as $name) {
            $class = self::MAP[$name] ?? null;

            if (! $class) {
                throw new InvalidArgumentException("Unknown metric: {$name}");
            }

            $out[$name] = app($class)->compute($workspaceId);
        }

        return $out;
    }

    public function keys(): array
    {
        return array_keys(self::MAP);
    }
}

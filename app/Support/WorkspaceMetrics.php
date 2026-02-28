<?php

namespace App\Support;

use App\Models\Workspace;
use App\Metrics\Orders\{AverageDeliveryDays,
    AverageLifetimeValue,
    RetentionRate,
    RtsRate,
    TimeToFirstOrder,
    TotalSales,
    TotalOrders,
    Aov};
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
     * @param string[] $names
     */
    public function extract(array $names): array
    {
        $out = [];
        $workspaceId = $this->workspace->id;

        foreach ($names as $name) {
            $class = self::MAP[$name] ?? null;

            if (!$class) {
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

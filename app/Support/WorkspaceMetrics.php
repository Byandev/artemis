<?php

namespace App\Support;

use App\Metrics\Orders\Aov;
use App\Metrics\Orders\AverageDaysFromConfirmedToDelivered;
use App\Metrics\Orders\AverageDaysFromConfirmedToFirstAttempt;
use App\Metrics\Orders\AverageDaysFromConfirmedToShipped;
use App\Metrics\Orders\AverageDaysFromReturningToReturned;
use App\Metrics\Orders\AverageDaysFromShippedToDelivered;
use App\Metrics\Orders\AverageDaysFromShippedToFirstAttempt;
use App\Metrics\Orders\AverageLifetimeValue;
use App\Metrics\Orders\DeliveredAmount;
use App\Metrics\Orders\DeliveredAvgCustomerRts;
use App\Metrics\Orders\DeliveredAvgDeliveryAttempts;
use App\Metrics\Orders\RepeatCustomerOrderCount;
use App\Metrics\Orders\RepeatCustomerRatio;
use App\Metrics\Orders\Retention30dRateCohort;
use App\Metrics\Orders\Retention60dRateCohort;
use App\Metrics\Orders\Retention90dRateCohort;
use App\Metrics\Orders\ReturnedAmount;
use App\Metrics\Orders\ReturnedAvgCustomerRts;
use App\Metrics\Orders\ReturnedAvgDeliveryAttempts;
use App\Metrics\Orders\ReturningAmount;
use App\Metrics\Orders\RtsRate;
use App\Metrics\Orders\TimeToFirstOrder;
use App\Metrics\Orders\TotalOrders;
use App\Metrics\Orders\TotalSales;
use App\Metrics\Orders\UniqueCustomerCount;
use App\Metrics\ParcelJourney\TrackedOrdersCount;
use App\Metrics\ParcelJourney\TotalForDeliveryCount;
use App\Models\Workspace;
use InvalidArgumentException;

final class WorkspaceMetrics
{
    public function __construct(
        private readonly Workspace $workspace,
        public array $dateRange,
        public array $filter
    ) {}

    /**
     * Registry: metricName => class
     */
    private const MAP = [
        'aov' => Aov::class,
        'rtsRate' => RtsRate::class,
        'totalSales' => TotalSales::class,
        'totalOrders' => TotalOrders::class,
        'uniqueCustomerCount' => UniqueCustomerCount::class,
        'repeatOrderRatio' => RepeatCustomerRatio::class,
        'repeatCustomerOrderCount'  => RepeatCustomerOrderCount::class,
        'retention30dRateCohort'    => Retention30dRateCohort::class,
        'retention60dRateCohort'    => Retention60dRateCohort::class,
        'retention90dRateCohort'    => Retention90dRateCohort::class,
        'timeToFirstOrder' => TimeToFirstOrder::class,
        'avgLifetimeValue' => AverageLifetimeValue::class,
        'averageDaysFromShippedToDelivered' => AverageDaysFromShippedToDelivered::class,
        'averageDaysFromConfirmedToShipped' => AverageDaysFromConfirmedToShipped::class,
        'averageDaysFromConfirmedToFirstAttempt' => AverageDaysFromConfirmedToFirstAttempt::class,
        'averageDaysFromShippedToFirstAttempt' => AverageDaysFromShippedToFirstAttempt::class,
        'averageDaysFromConfirmedToDelivered' => AverageDaysFromConfirmedToDelivered::class,
        'averageDaysFromReturningToReturned' => AverageDaysFromReturningToReturned::class,
        'deliveredAmount' => DeliveredAmount::class,
        'returnedAmount' => ReturnedAmount::class,
        'returningAmount' => ReturningAmount::class,
        'trackedOrdersCount' => TrackedOrdersCount::class,
        'totalForDeliveryCount' => TotalForDeliveryCount::class,
        'deliveredAvgCustomerRts' => DeliveredAvgCustomerRts::class,
        'returnedAvgCustomerRts' => ReturnedAvgCustomerRts::class,
        'deliveredAvgDeliveryAttempts' => DeliveredAvgDeliveryAttempts::class,
        'returnedAvgDeliveryAttempts' => ReturnedAvgDeliveryAttempts::class,
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

            $out[$name] = app($class)->compute(
                $workspaceId,
                $this->dateRange,
                $this->filter
            );
        }

        return $out;
    }

    public function breakdown(string $name, string $group = 'monthly')
    {
        $class = self::MAP[$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown metric: {$name}");
        }

        $workspaceId = $this->workspace->id;

        if (! method_exists($class, 'breakdown')) {
            throw new InvalidArgumentException("Metric {$name} does not support breakdown.");
        }

        return app($class)->breakdown(
            $workspaceId,
            $this->dateRange,
            $this->filter,
            $group
        );
    }

    public function perPage(string $name)
    {
        $class = self::MAP[$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown metric: {$name}");
        }

        $workspaceId = $this->workspace->id;

        if (! method_exists($class, 'perPage')) {
            throw new InvalidArgumentException("Metric {$name} does not support perPage.");
        }

        return app($class)->perPage(
            $workspaceId,
            $this->dateRange,
            $this->filter
        );
    }

    public function perShop(string $name)
    {
        $class = self::MAP[$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown metric: {$name}");
        }

        $workspaceId = $this->workspace->id;

        if (! method_exists($class, 'perShop')) {
            throw new InvalidArgumentException("Metric {$name} does not support perShop.");
        }

        return app($class)->perShop(
            $workspaceId,
            $this->dateRange,
            $this->filter
        );
    }

    public function perUser(string $name)
    {
        $class = self::MAP[$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown metric: {$name}");
        }

        $workspaceId = $this->workspace->id;

        if (! method_exists($class, 'perUser')) {
            throw new InvalidArgumentException("Metric {$name} does not support perUser.");
        }

        return app($class)->perUser(
            $workspaceId,
            $this->dateRange,
            $this->filter
        );
    }

    public function keys(): array
    {
        return array_keys(self::MAP);
    }
}

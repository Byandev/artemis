<?php

namespace App\Support\Metrics;

use Illuminate\Support\Str;

class MetricRegistry
{
    public const KEYS = [
        // Revenue & Volume
        'aov' => 'revenueVolume',
        'totalSales' => 'revenueVolume',
        'totalOrders' => 'revenueVolume',
        'deliveredAmount' => 'revenueVolume',
        'returnedAmount' => 'revenueVolume',
        'returningAmount' => 'revenueVolume',
        'totalForDeliveryCount' => 'revenueVolume',
        'totalForDeliveryAmount' => 'revenueVolume',

        // Ratios & Performance
        'rtsRate' => 'deliveryOutcomes',
        'repeatOrderRatio' => 'customerQualityRetention',
        'repeatCustomerRatio' => 'customerQualityRetention',
        'uniqueCustomerCount' => 'customerQualityRetention',
        'repeatCustomerOrderCount' => 'customerQualityRetention',

        // Lead Times (Fulfillment)
        'fulfillment_time' => 'fulfillmentLeadTime',
        'timeToFirstOrder' => 'fulfillmentLeadTime',
        'averageDaysFromShippedToDelivered' => 'fulfillmentLeadTime',
        'averageDaysFromConfirmedToShipped' => 'fulfillmentLeadTime',
        'averageDaysFromConfirmedToFirstAttempt' => 'fulfillmentLeadTime',
        'averageDaysFromShippedToFirstAttempt' => 'fulfillmentLeadTime',
        'averageDaysFromConfirmedToDelivered' => 'fulfillmentLeadTime',
        'averageDaysFromReturningToReturned' => 'fulfillmentLeadTime',

        // Delivery Quality
        'deliveredAvgCustomerRts' => 'deliveryQualitySignals',
        'returnedAvgCustomerRts' => 'deliveryQualitySignals',
        'deliveredAvgDeliveryAttempts' => 'deliveryQualitySignals',
        'returnedAvgDeliveryAttempts' => 'deliveryQualitySignals',

        // Cohorts / Retention
        'retention30dRateCohort' => 'customerQualityRetention',
        'retention60dRateCohort' => 'customerQualityRetention',
        'retention90dRateCohort' => 'customerQualityRetention',
    ];

    /**
     * Used for validation and simple lists.
     */
    public static function all(): array
    {
        return array_keys(self::KEYS);
    }

    /**
     * This feeds your Frontend 'configs' prop.
     * Generates: [{ key: 'aov', groupKey: 'revenueVolume', name: 'Aov' }, ...]
     */
    public static function configs(): array
    {
        return collect(self::KEYS)->map(function ($group, $key) {
            return [
                'key' => $key,
                'groupKey' => $group,
                'name' => Str::headline($key), // Converts 'totalSales' to 'Total Sales'
            ];
        })->values()->all();
    }

    public static function defaults(): array
    {
        return ['totalSales', 'totalOrders', 'aov', 'rtsRate'];
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::KEYS);
    }
}

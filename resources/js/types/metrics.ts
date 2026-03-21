import {
    currencyFormatter,
    numberFormatter,
    percentageFormatter,
} from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

export type MetricKey =
    | 'aov'
    | 'rtsRate'
    | 'totalSales'
    | 'totalOrders'
    | 'repeatOrderRatio'
    | 'timeToFirstOrder'
    | 'avgLifetimeValue'
    | 'averageDaysFromShippedToDelivered'
    | 'averageDaysFromConfirmedToShipped'
    | 'averageDaysFromConfirmedToFirstAttempt'
    | 'averageDaysFromShippedToFirstAttempt'
    | 'averageDaysFromConfirmedToDelivered'
    | 'averageDaysFromReturningToReturned'
    | 'deliveredAmount'
    | 'returnedAmount'
    | 'returningAmount'
    | 'deliveredAvgCustomerRts'
    | 'returnedAvgCustomerRts'
    | 'deliveredAvgDeliveryAttempts'
    | 'returnedAvgDeliveryAttempts';

export type MetricGroupKey =
    | 'revenueVolume'
    | 'fulfillmentLeadTime'
    | 'deliveryOutcomes'
    | 'customerQualityRetention'
    | 'deliveryQualitySignals';

export type MetricConfig = {
    key: MetricKey;
    groupKey: MetricGroupKey;
    name: string;
    description: string;
    formatter: (value: number) => string;
    icon?: LucideIcon | null;
    reverse?: boolean
};

export const metricConfigs: MetricConfig[] = [
    {
        key: 'totalSales',
        groupKey: 'revenueVolume',
        name: 'Total Sales',
        description: 'Total sales amount within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'totalOrders',
        groupKey: 'revenueVolume',
        name: 'Total Orders',
        description: 'Total number of orders within the selected date range.',
        formatter: numberFormatter,
    },
    {
        key: 'aov',
        groupKey: 'revenueVolume',
        name: 'AOV',
        description: 'Average order value within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'averageDaysFromConfirmedToShipped',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - Shipped',
        description: 'Average time from confirmed to shipped.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromConfirmedToFirstAttempt',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - 1st Delivery',
        description: 'Average time from confirmed to first delivery attempt.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromShippedToFirstAttempt',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Shipped - 1st Delivery',
        description: 'Average time from shipped to first delivery attempt.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromShippedToDelivered',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Shipped - Delivered',
        description: 'Average time from shipped to delivered.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromConfirmedToDelivered',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - Delivered',
        description: 'Average time from confirmed to delivered.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromReturningToReturned',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Returning - Returned',
        description: 'Average time from returning to returned.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'deliveredAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Delivered Amount',
        description: 'Total delivered sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'returningAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Returning Amount',
        description: 'Total returning sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'returnedAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Returned Amount',
        description: 'Total returned sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'rtsRate',
        groupKey: 'deliveryOutcomes',
        name: 'RTS Rate',
        description:
            'Return-to-sender rate based on returning and returned amount versus total outcome amount.',
        formatter: percentageFormatter,
        reverse: true,
    },
    {
        key: 'repeatOrderRatio',
        groupKey: 'customerQualityRetention',
        name: 'Repeat Order Ratio',
        description: 'Percentage of orders coming from repeat customers.',
        formatter: percentageFormatter,
    },
    {
        key: 'timeToFirstOrder',
        groupKey: 'customerQualityRetention',
        name: 'Time to First Order',
        description:
            'Average time it takes for a customer to place their first order.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'avgLifetimeValue',
        groupKey: 'customerQualityRetention',
        name: 'Average Lifetime Value',
        description: 'Average lifetime value per customer.',
        formatter: currencyFormatter,
    },

    {
        key: 'deliveredAvgCustomerRts',
        groupKey: 'deliveryQualitySignals',
        name: 'Delivered Avg Customer RTS',
        description: 'Average historical customer RTS of delivered orders.',
        formatter: percentageFormatter,
    },
    {
        key: 'returnedAvgCustomerRts',
        groupKey: 'deliveryQualitySignals',
        name: 'Returned Avg Customer RTS',
        description: 'Average historical customer RTS of returned orders.',
        formatter: percentageFormatter,
        reverse: true,
    },
    {
        key: 'deliveredAvgDeliveryAttempts',
        groupKey: 'deliveryQualitySignals',
        name: 'Delivered Avg Delivery Attempts',
        description: 'Average delivery attempts for delivered orders.',
        formatter: numberFormatter,
        reverse: true,
    },
    {
        key: 'returnedAvgDeliveryAttempts',
        groupKey: 'deliveryQualitySignals',
        name: 'Returned Avg Delivery Attempts',
        description: 'Average delivery attempts for returned orders.',
        formatter: numberFormatter,
        reverse: true,
    },
];

export const metricGroups = [
    {
        key: 'revenueVolume',
        label: 'Revenue & Volume',
    },
    {
        key: 'fulfillmentLeadTime',
        label: 'Fulfillment Lead Time',
    },
    {
        key: 'deliveryOutcomes',
        label: 'Delivery Outcomes',
    },
    {
        key: 'customerQualityRetention',
        label: 'Customer Quality & Retention',
    },
    {
        key: 'deliveryQualitySignals',
        label: 'Delivery Quality Signals',
    },
] as const;

export const groupedMetrics = metricGroups.map((group) => ({
    ...group,
    metrics: metricConfigs.filter((metric) => metric.groupKey === group.key),
}));

import type { LucideIcon } from 'lucide-react';
import { currencyFormatter, percentageFormatter } from '@/lib/utils';

type MetricKey =
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
    | 'trackedOrdersCount'
    | 'deliveredAvgCustomerRts'
    | 'returnedAvgCustomerRts';

type MetricConfig = {
    key: MetricKey;
    name: string;
    description: string;
    formatter: (value: number) => string;
    icon?: LucideIcon | null;
};

export const metricConfigs: MetricConfig[] = [
    {
        key: 'rtsRate',
        name: 'RTS Rate',
        description:
            'Return-to-sender rate based on returned amount versus total amount.',
        formatter: percentageFormatter,
    },
    {
        key: 'deliveredAmount',
        name: 'Delivered Amount',
        description: 'Total delivered sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'returningAmount',
        name: 'Returning Amount',
        description: 'Total returning sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'returnedAmount',
        name: 'Returned Amount',
        description: 'Total returned sales within the selected date range.',
        formatter: currencyFormatter,
    },
    {
        key: 'averageDaysFromConfirmedToShipped',
        name: 'Ave. Confirmed - Shipped',
        description: 'Average time from confirmed to shipped.',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'averageDaysFromConfirmedToFirstAttempt',
        name: 'Ave. Confirmed - 1st Delivery',
        description: 'Average time from confirmed to first delivery attempt.',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'averageDaysFromConfirmedToDelivered',
        name: 'Ave. Confirmed - Delivered',
        description: 'Average time from confirmed to delivered',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'averageDaysFromShippedToFirstAttempt',
        name: 'Ave. Shipped - 1st Delivery attempt',
        description: 'Average time from shipped to 1st delivery.',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'averageDaysFromShippedToDelivered',
        name: 'Ave. Shipped - Delivered',
        description: 'Average time from shipment to successful delivery.',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'averageDaysFromReturningToReturned',
        name: 'Ave. Returning - Returned',
        description: 'Average time from shipment to successful delivery.',
        formatter: (value: number) => `${value} days`,
    },
    {
        key: 'deliveredAvgCustomerRts',
        name: 'Delivered Avg Customer RTS',
        description: 'Average historical customer RTS of delivered orders',
        formatter: percentageFormatter,
    },
    {
        key: 'returnedAvgCustomerRts',
        name: 'Returned Avg Customer RTS',
        description: 'Average historical customer RTS of returned orders.',
        formatter: percentageFormatter
    },
];

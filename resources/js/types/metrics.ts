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
    | 'uniqueCustomerCount'
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
    | 'returnedAvgDeliveryAttempts'
    | 'totalForDeliveryCount'
    | 'repeatCustomerOrderCount'
    | 'retention30dRateCohort'
    | 'retention60dRateCohort'
    | 'retention90dRateCohort';

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
        description: 'Total revenue earned from all orders in the selected period.',
        formatter: currencyFormatter,
    },
    {
        key: 'totalOrders',
        groupKey: 'revenueVolume',
        name: 'Total Orders',
        description: 'Total number of orders placed in the selected period.',
        formatter: numberFormatter,
    },
    {
        key: 'uniqueCustomerCount',
        groupKey: 'revenueVolume',
        name: 'Unique Customers',
        description: 'How many different customers placed at least one order in the selected period.',
        formatter: numberFormatter,
    },
    {
        key: 'aov',
        groupKey: 'revenueVolume',
        name: 'AOV',
        description: 'On average, how much does a customer spend per order in the selected period.',
        formatter: currencyFormatter,
    },
    {
        key: 'averageDaysFromConfirmedToShipped',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - Shipped',
        description: 'On average, how many days it takes to ship an order after it is confirmed.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromConfirmedToFirstAttempt',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - 1st Delivery',
        description: 'On average, how many days it takes from confirming an order to the courier\'s first delivery attempt.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromShippedToFirstAttempt',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Shipped - 1st Delivery',
        description: 'On average, how many days it takes from shipping to the courier\'s first delivery attempt.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromShippedToDelivered',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Shipped - Delivered',
        description: 'On average, how many days it takes for a package to be delivered after it is shipped.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromConfirmedToDelivered',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Confirmed - Delivered',
        description: 'On average, how many days it takes from confirming an order to it being delivered to the customer.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'averageDaysFromReturningToReturned',
        groupKey: 'fulfillmentLeadTime',
        name: 'Ave. Returning - Returned',
        description: 'On average, how many days it takes for a return to be completed after it is initiated.',
        formatter: (value: number) => `${value} days`,
        reverse: true,
    },
    {
        key: 'deliveredAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Delivered Amount',
        description: 'Total value of orders that were successfully delivered in the selected period.',
        formatter: currencyFormatter,
    },
    {
        key: 'returningAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Returning Amount',
        description: 'Total value of orders that are currently on their way back to the seller.',
        formatter: currencyFormatter,
    },
    {
        key: 'returnedAmount',
        groupKey: 'deliveryOutcomes',
        name: 'Returned Amount',
        description: 'Total value of orders that have been returned to the seller in the selected period.',
        formatter: currencyFormatter,
    },
    {
        key: 'rtsRate',
        groupKey: 'deliveryOutcomes',
        name: 'RTS Rate',
        description:
            'Out of all orders with a delivery outcome, what percentage were returned instead of delivered.',
        formatter: percentageFormatter,
        reverse: true,
    },
    {
        key: 'repeatOrderRatio',
        groupKey: 'customerQualityRetention',
        name: 'Repeat Customer Ratio',
        description: 'Out of all unique customers in the selected period, what percentage have ordered more than once.',
        formatter: percentageFormatter,
    },
    {
        key: 'timeToFirstOrder',
        groupKey: 'customerQualityRetention',
        name: 'Time to First Order',
        description:
            'On average, how many hours it takes from a customer signing up to placing their very first order.',
        formatter: (value: number) => `${value} hrs`,
        reverse: true,
    },
    {
        key: 'avgLifetimeValue',
        groupKey: 'customerQualityRetention',
        name: 'Average Lifetime Value',
        description: 'On average, how much a customer has spent in total across all their orders.',
        formatter: currencyFormatter,
    },

    {
        key: 'deliveredAvgCustomerRts',
        groupKey: 'deliveryQualitySignals',
        name: 'Delivered Avg Customer RTS',
        description: 'For orders that were delivered, what is the average RTS history of those customers. Lower is better.',
        formatter: percentageFormatter,
    },
    {
        key: 'returnedAvgCustomerRts',
        groupKey: 'deliveryQualitySignals',
        name: 'Returned Avg Customer RTS',
        description: 'For orders that were returned, what is the average RTS history of those customers. Higher means riskier buyers.',
        formatter: percentageFormatter,
        reverse: true,
    },
    {
        key: 'deliveredAvgDeliveryAttempts',
        groupKey: 'deliveryQualitySignals',
        name: 'Delivered Avg Delivery Attempts',
        description: 'For orders that were delivered, how many delivery attempts it took on average.',
        formatter: numberFormatter,
        reverse: true,
    },
    {
        key: 'returnedAvgDeliveryAttempts',
        groupKey: 'deliveryQualitySignals',
        name: 'Returned Avg Delivery Attempts',
        description: 'For orders that were returned, how many delivery attempts were made before giving up.',
        formatter: numberFormatter,
        reverse: true,
    },
    {
        key: 'repeatCustomerOrderCount',
        groupKey: 'customerQualityRetention',
        name: 'Repeat Unique Customers',
        description: 'How many customers ordered in the selected period and have placed 2 or more orders in total up to that point.',
        formatter: numberFormatter,
    },
    {
        key: 'retention30dRateCohort',
        groupKey: 'customerQualityRetention',
        name: '30-Day Retention Rate',
        description: 'Out of all customers who ordered in the selected period, what percentage had 2 or more orders in the 30 days leading up to their latest order.',
        formatter: percentageFormatter,
    },
    {
        key: 'retention60dRateCohort',
        groupKey: 'customerQualityRetention',
        name: '60-Day Retention Rate',
        description: 'Out of all customers who ordered in the selected period, what percentage had 2 or more orders in the 60 days leading up to their latest order.',
        formatter: percentageFormatter,
    },
    {
        key: 'retention90dRateCohort',
        groupKey: 'customerQualityRetention',
        name: '90-Day Retention Rate',
        description: 'Out of all customers who ordered in the selected period, what percentage had 2 or more orders in the 90 days leading up to their latest order.',
        formatter: percentageFormatter,
    },
    {
        key: 'totalForDeliveryCount',
        groupKey: 'deliveryOutcomes',
        name: 'For Delivery Count',
        description: 'How many orders are currently out for delivery in the selected period.',
        formatter: numberFormatter,
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

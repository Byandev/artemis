import type { ChartConfig } from '@/components/ui/chart';

export const STATUS_COLORS: Record<string, string> = {
    PENDING: '#eab308',
    DELIVERED: '#10b981',
    'RIDER OTW': '#3b82f6',
    RETURNING: '#f97316',
    RESCHEDULED: '#a855f7',
    'CX CBR': '#ef4444',
    'RIDER CBR': '#dc2626',
    CANCELLED: '#9ca3af',
    'WRONG SEGMENT CODE': '#f43f5e',
    'CX RINGING': '#f59e0b',
    'RIDER RINGING': '#d97706',
    'IN TRANSIT': '#06b6d4',
    'INCORRECT NUMBER': '#ec4899',
    'AUTO DROP CX': '#be185d',
    'AUTO DROP RIDER': '#6366f1',
};

export const FALLBACK_COLOR = '#71717a';

export const salesConfig: ChartConfig = {
    sales: { label: 'Sales (₱)', theme: { light: '#10d3a1', dark: '#10d3a1' } },
    orders: { label: 'Orders', theme: { light: '#0ba5ec', dark: '#36bffa' } },
};

export const deliveryConfig: ChartConfig = {
    delivered: { label: 'Delivered', theme: { light: '#10b981', dark: '#34d399' } },
    returning: { label: 'Returning', theme: { light: '#f97316', dark: '#fb923c' } },
};

export const rtsConfig: ChartConfig = {
    rts_rate: { label: 'RTS Rate %', theme: { light: '#ef4444', dark: '#f87171' } },
};

export const statusConfig: ChartConfig = Object.fromEntries(
    Object.entries(STATUS_COLORS).map(([k, v]) => [k, { label: k, color: v }]),
);

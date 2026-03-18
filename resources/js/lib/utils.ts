import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { format, differenceInDays } from 'date-fns';
import { type DateRange } from 'react-day-picker';
import { InertiaLinkProps } from '@inertiajs/react';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Format a number with locale-specific formatting
 * @param value - The number to format
 * @param options - Intl.NumberFormat options
 * @returns Formatted number string
 */
export function numberFormatter(
    value: number,
    options?: Intl.NumberFormatOptions
): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
        ...options,
    }).format(value);
}

/**
 * Format a number as a percentage
 * @param value - The number to format (e.g., 0.5 for 50%)
 * @param options - Intl.NumberFormat options
 * @returns Formatted percentage string
 */
export function percentageFormatter(
    value: number,
    options?: Intl.NumberFormatOptions
): string {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
        ...options,
    }).format(value);
}

/**
 * Format a number as currency (default: Philippine Peso)
 * @param value - The number to format
 * @param options - Intl.NumberFormat options
 * @returns Formatted currency string
 */
export function currencyFormatter(
    value: number,
    options?: Intl.NumberFormatOptions
): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        ...options,
    }).format(value);
}

/**
 * Generate a description for a date range
 * @param dateRange - The date range object with from/to dates
 * @param fallback - Fallback text when no date range is provided (default: 'Last 30 days')
 * @returns Formatted description string
 */
export function getDateRangeDescription(
    dateRange: DateRange | undefined,
    fallback: string = 'Last 30 days'
): string {
    if (dateRange?.from && dateRange?.to) {
        return `${format(dateRange.from, 'MMM d, yyyy')} - ${format(dateRange.to, 'MMM d, yyyy')}`;
    } else if (dateRange?.from) {
        return `From ${format(dateRange.from, 'MMM d, yyyy')}`;
    } else if (dateRange?.to) {
        return `Until ${format(dateRange.to, 'MMM d, yyyy')}`;
    }
    return fallback;
}


/**
 * Resolve an Inertia/URL string to a pathname.
 * If `url` is empty, returns the current `window.location.pathname` when available.
 * This centralizes the try/new URL parsing used across components.
 */
export function extractPathFromUrl(url?: string | null): string {
    if (!url) return typeof window !== 'undefined' ? window.location.pathname : '/';
    const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    try {
        return new URL(url, base).pathname;
    } catch {
        return url;
    }
}


export function formatDate(date: Date | undefined) {
    if (!date) {
        return ""
    }

    return date.toLocaleDateString("en-US", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    })
}

export function isValidDate(date: Date | undefined) {
    if (!date) {
        return false
    }
    return !isNaN(date.getTime())
}


export function formatCompactCurrency(value: number){
    const n = Number(value) || 0;
    const abs = Math.abs(n);

    const fmt = (v: number, suffix: string) => {
        // show 1 decimal only when needed (e.g., 1.2M), but 1M stays 1M
        const rounded = v % 1 === 0 ? v.toFixed(0) : v.toFixed(1);
        return `${n < 0 ? "-" : ""}${rounded}${suffix}`;
    };

    if (abs >= 1_000_000) return `₱ ${fmt(abs / 1_000_000, "M")}`;
    if (abs >= 1_000)     return `₱ ${fmt(abs / 1_000_000, "M")}`;

    return `₱ ${n}`;
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

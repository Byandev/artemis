import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

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
 * @param currency - Currency code (default: 'PHP')
 * @param options - Intl.NumberFormat options
 * @returns Formatted currency string
 */
export function currencyFormatter(
    value: number,
    currency: string = 'PHP',
    options?: Intl.NumberFormatOptions
): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        ...options,
    }).format(value);
}

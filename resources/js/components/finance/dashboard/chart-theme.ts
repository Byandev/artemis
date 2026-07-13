import { type ApexOptions } from 'apexcharts';

/**
 * Shared tooltip styling for every finance dashboard chart: a solid light card
 * so hovering always shows a readable, consistently-styled popover.
 */
export const tooltipTheme: ApexOptions['tooltip'] = {
    theme: 'light',
    style: { fontSize: '12px', fontFamily: 'DM Sans, sans-serif' },
    fillSeriesColor: false,
    marker: { show: true },
};

/** Palette for categorical breakdowns (expenses / income). */
export const CATEGORY_COLORS = [
    '#6366f1',
    '#10b981',
    '#f59e0b',
    '#ef4444',
    '#06b6d4',
    '#8b5cf6',
    '#ec4899',
    '#84cc16',
    '#94a3b8',
];

import { type ApexOptions } from 'apexcharts';

/**
 * Shared tooltip styling for every dashboard chart: a solid light card with a
 * subtle shadow and series markers, so hovering always shows a readable,
 * consistently-styled popover (never a transparent/unstyled default).
 */
export const tooltipTheme: ApexOptions['tooltip'] = {
    theme: 'light',
    style: { fontSize: '12px', fontFamily: 'DM Sans, sans-serif' },
    fillSeriesColor: false,
    marker: { show: true },
};

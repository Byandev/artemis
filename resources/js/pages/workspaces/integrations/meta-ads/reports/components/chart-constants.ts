/**
 * Shared presentation constants for the report builder's gallery and charts.
 * Kept theme-token based so everything tracks the Artemis light/dark palette.
 */

// Gallery card size → grid columns. Bigger card size = fewer, larger columns.
export const GALLERY_GRID: Record<number, string> = {
    1: 'grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6',
    2: 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4',
    3: 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3',
};

// Series palette drawn from the app's theme tokens so charts match Artemis and
// adapt to light/dark automatically. `--primary` is the emerald accent; the
// `--chart-*` tokens are the shadcn chart ramp defined in app.css.
export const CHART_COLORS = [
    'var(--primary)',
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export const chartColor = (i: number): string =>
    CHART_COLORS[i % CHART_COLORS.length];

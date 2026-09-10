export type HeatMetric =
    | 'returning_count'
    | 'returning_amount'
    | 'rts_rate_percentage'
    | 'orders';

/** How a metric's numbers read: whole things, money, or a percentage. */
export type MetricKind = 'count' | 'currency' | 'percent';

export interface HeatMetricConfig {
    key: HeatMetric;
    label: string;
    short: string;
    kind: MetricKind;
    /** Full precision, for the tooltip and the ranked list. */
    format: (value: number) => string;
}

const count = (v: number) => Math.round(v).toLocaleString();

const peso = (v: number) => `₱${Math.round(v).toLocaleString()}`;

/** Short enough for a legend swatch: ₱1.4M, ₱12k, ₱850. */
const pesoCompact = (v: number) => {
    if (v >= 1_000_000) return `₱${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `₱${Math.round(v / 1_000).toLocaleString()}k`;
    return `₱${Math.round(v)}`;
};

export const HEAT_METRICS: HeatMetricConfig[] = [
    {
        key: 'returning_count',
        label: 'RTS Count',
        short: 'Returned orders',
        kind: 'count',
        format: count,
    },
    {
        key: 'returning_amount',
        label: 'RTS Value',
        short: 'Returned value',
        kind: 'currency',
        format: peso,
    },
    {
        key: 'rts_rate_percentage',
        label: 'RTS Rate',
        short: 'RTS rate',
        kind: 'percent',
        format: (v) => `${v.toFixed(1)}%`,
    },
    {
        key: 'orders',
        label: 'Orders',
        short: 'Orders',
        kind: 'count',
        format: count,
    },
];

export interface Bucket {
    /** Inclusive lower bound. */
    from: number;
    /** Exclusive upper bound; null on the open-ended top bucket. */
    to: number | null;
    color: string;
    label: string;
}

export interface HeatScale {
    buckets: Bucket[];
    colorFor: (value: number) => string;
}

/**
 * RTS rate has meaning independent of the data — 30% is bad whatever else is on
 * the map — so it gets fixed thresholds, keeping the colours comparable between
 * date ranges. Hue runs green→red to match the RTS rate colouring used in the
 * analytics tables.
 */
const RATE_STOPS = [0, 10, 15, 20, 25, 35];

const RATE_COLORS = [
    '#15803d',
    '#65a30d',
    '#ca8a04',
    '#ea580c',
    '#dc2626',
    '#991b1b',
];

/**
 * Counts and money have no absolute meaning, and are heavily skewed — a handful
 * of cities carry most of the volume — so a linear ramp would leave the whole map
 * at the pale end. Quantiles give every bucket a comparable share of the areas.
 *
 * Light theme darkens as the value climbs; dark theme brightens instead, so on
 * both the "hot" areas are the ones with the most contrast against the page.
 */
const COUNT_COLORS_LIGHT = [
    '#fecaca',
    '#fca5a5',
    '#f87171',
    '#ef4444',
    '#dc2626',
    '#991b1b',
];

const COUNT_COLORS_DARK = [
    '#450a0a',
    '#7f1d1d',
    '#b91c1c',
    '#ef4444',
    '#f87171',
    '#fca5a5',
];

export const NO_DATA_COLOR = { light: '#dcdce1', dark: '#27272a' };
export const BORDER_COLOR = { light: '#ffffff', dark: '#18181b' };

export function buildScale(
    values: number[],
    kind: MetricKind,
    isDark: boolean,
): HeatScale {
    if (kind === 'percent') {
        return fromStops(RATE_STOPS, RATE_COLORS, kind);
    }

    const palette = isDark ? COUNT_COLORS_DARK : COUNT_COLORS_LIGHT;
    const sorted = values.filter((v) => v > 0).sort((a, b) => a - b);

    if (sorted.length === 0) {
        return { buckets: [], colorFor: () => palette[0] };
    }

    // Quantile edges, de-duplicated so a long tail of 1s doesn't create several
    // buckets that all start at the same number.
    const stops = Array.from(
        new Set(
            Array.from({ length: palette.length }, (_, i) =>
                Math.floor(
                    sorted[Math.floor((i * sorted.length) / palette.length)],
                ),
            ),
        ),
    ).sort((a, b) => a - b);

    return fromStops(stops, palette.slice(palette.length - stops.length), kind);
}

function fromStops(
    stops: number[],
    colors: string[],
    kind: MetricKind,
): HeatScale {
    const buckets: Bucket[] = stops.map((from, i) => {
        const to = i < stops.length - 1 ? stops[i + 1] : null;

        return {
            from,
            to,
            color: colors[i] ?? colors[colors.length - 1],
            label: label(from, to, kind),
        };
    });

    return {
        buckets,
        colorFor: (value: number) => {
            for (let i = buckets.length - 1; i >= 0; i--) {
                if (value >= buckets[i].from) return buckets[i].color;
            }
            return buckets[0]?.color ?? colors[0];
        },
    };
}

/**
 * Buckets are half-open — [from, to) — so a whole-number range prints its real
 * last value ("3–4", not "3–5") and a single-value bucket prints just the number.
 * Money and percentages are continuous, so they keep the boundary as written.
 */
function label(from: number, to: number | null, kind: MetricKind): string {
    if (kind === 'percent') {
        return to === null ? `${from}%+` : `${from}–${to}%`;
    }

    if (kind === 'currency') {
        return to === null
            ? `${pesoCompact(from)}+`
            : `${pesoCompact(from)}–${pesoCompact(to)}`;
    }

    if (to === null) {
        return `${count(from)}+`;
    }

    return to - from === 1 ? count(from) : `${count(from)}–${count(to - 1)}`;
}

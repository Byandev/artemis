/**
 * What the map is coloured by.
 *
 * `rts` shades on RTS rate alone, as a continuous ramp. `rts-orders` sorts each
 * area into one of the four CSR monitoring groups — RTS rate crossed with order
 * volume — so the colour maps to an action rather than to a number.
 */
export type HeatMode = 'rts' | 'rts-orders';

export interface HeatModeConfig {
    key: HeatMode;
    label: string;
    /** Reads as "<this> by province" in the card subtitle. */
    short: string;
}

export const HEAT_MODES: HeatModeConfig[] = [
    { key: 'rts', label: 'RTS', short: 'RTS rate' },
    { key: 'rts-orders', label: 'RTS + Orders', short: 'CSR monitoring group' },
];

/* ------------------------------------------------------------------ *
 * Mode 1 — RTS rate only
 * ------------------------------------------------------------------ */

/**
 * RTS rate has meaning independent of the data — 30% is bad whatever else is on
 * the map — so the bands are fixed rather than derived, and a province is the
 * same colour in June as in August.
 */
const RATE_STOPS = [0, 10, 15, 22, 30, 40];

const RATE_COLORS = [
    '#15803d',
    '#65a30d',
    '#ca8a04',
    '#ea580c',
    '#dc2626',
    '#991b1b',
];

/* ------------------------------------------------------------------ *
 * Mode 2 — CSR monitoring groups
 * ------------------------------------------------------------------ */

export type CsrGroupKey = 'red' | 'orange' | 'yellow' | 'green';

export interface CsrGroup {
    key: CsrGroupKey;
    /** What the group is, in terms of the two inputs. */
    label: string;
    color: string;
    /** What a CSR should do with orders from here. */
    strategy: string;
}

export const CSR_GROUPS: CsrGroup[] = [
    {
        key: 'red',
        label: 'High RTS + High Volume',
        color: '#d0342c',
        strategy: 'Double confirm; ship only validated orders',
    },
    {
        key: 'orange',
        label: 'High RTS + Low Volume',
        color: '#ef7d1a',
        strategy: 'Strict validation, but low operational priority',
    },
    {
        key: 'yellow',
        label: 'High Volume + Moderate RTS',
        color: '#f0c330',
        strategy: 'Normal + strong address / COD confirmation',
    },
    {
        key: 'green',
        label: 'Low RTS',
        color: '#57ab5a',
        strategy: 'Standard processing / scale normally',
    },
];

/**
 * Where the group boundaries sit.
 *
 * Rate bands are absolute, so a province keeps its group as the date range moves:
 * under 15% is low, 15–22% moderate, above 22% high.
 *
 * "High volume" cannot be absolute — a fortnight and a quarter are not comparable
 * in orders — so it is a percentile of the areas that have any orders at all,
 * floored so a quiet range cannot promote three orders to "high".
 */
export const CSR_THRESHOLDS = {
    /** Above this is high; 15–22 inclusive is moderate. */
    highRtsRate: 22,
    moderateRtsRate: 15,
    highVolumePercentile: 0.75,
    minimumHighVolume: 20,
};

export const NO_DATA_COLOR = { light: '#dcdce1', dark: '#3f3f46' };
export const BORDER_COLOR = { light: '#ffffff', dark: '#18181b' };

export interface Bucket {
    /** Inclusive lower bound. */
    from: number;
    /** Exclusive upper bound; null on the open-ended top bucket. */
    to: number | null;
    color: string;
    label: string;
}

export interface HeatScale {
    /** The rate ramp — only in `rts`. */
    buckets: Bucket[] | null;
    /** The CSR groups — only in `rts-orders`. */
    groups: CsrGroup[] | null;
    /** Orders at or above this count count as high volume; null in `rts`. */
    highVolumeFrom: number | null;
    colorFor: (ratePercentage: number, orders: number) => string;
    /** Which CSR group an area falls in; null in `rts`. */
    groupFor: (ratePercentage: number, orders: number) => CsrGroupKey | null;
}

export function buildScale(
    orderCounts: number[],
    mode: HeatMode,
    isDark: boolean,
): HeatScale {
    if (mode === 'rts') {
        const buckets: Bucket[] = RATE_STOPS.map((from, i) => ({
            from,
            to: i < RATE_STOPS.length - 1 ? RATE_STOPS[i + 1] : null,
            color: RATE_COLORS[i],
            label:
                i < RATE_STOPS.length - 1
                    ? `${from}–${RATE_STOPS[i + 1]}%`
                    : `${from}%+`,
        }));

        return {
            buckets,
            groups: null,
            highVolumeFrom: null,
            groupFor: () => null,
            colorFor: (rate) => {
                for (let i = buckets.length - 1; i >= 0; i--) {
                    if (rate >= buckets[i].from) return buckets[i].color;
                }
                return buckets[0].color;
            },
        };
    }

    const highVolumeFrom = highVolumeThreshold(orderCounts);
    const byKey = Object.fromEntries(CSR_GROUPS.map((g) => [g.key, g.color]));
    const noData = isDark ? NO_DATA_COLOR.dark : NO_DATA_COLOR.light;

    const groupFor = (rate: number, orders: number): CsrGroupKey | null => {
        if (orders <= 0) return null;

        const highVolume = orders >= highVolumeFrom;

        // Strictly above: 22% itself is the top of the moderate band.
        if (rate > CSR_THRESHOLDS.highRtsRate) {
            return highVolume ? 'red' : 'orange';
        }

        // Moderate rate only earns its own group where the volume makes it cost
        // real money; quieter areas fall through to standard processing.
        if (rate >= CSR_THRESHOLDS.moderateRtsRate && highVolume) {
            return 'yellow';
        }

        return 'green';
    };

    return {
        buckets: null,
        groups: CSR_GROUPS,
        highVolumeFrom,
        groupFor,
        colorFor: (rate, orders) => {
            const key = groupFor(rate, orders);
            return key ? byKey[key] : noData;
        },
    };
}

/**
 * The 75th percentile of areas that have orders, but never below the floor — in a
 * one-week range the top quarter of provinces might only have a handful of orders
 * each, which is not "high volume" in any useful sense.
 */
function highVolumeThreshold(orderCounts: number[]): number {
    const sorted = orderCounts.filter((v) => v > 0).sort((a, b) => a - b);

    if (sorted.length === 0) {
        return CSR_THRESHOLDS.minimumHighVolume;
    }

    const at = Math.floor(sorted.length * CSR_THRESHOLDS.highVolumePercentile);
    const percentile = sorted[Math.min(at, sorted.length - 1)];

    return Math.max(percentile, CSR_THRESHOLDS.minimumHighVolume);
}

export const peso = (value: number) => `₱${Math.round(value).toLocaleString()}`;

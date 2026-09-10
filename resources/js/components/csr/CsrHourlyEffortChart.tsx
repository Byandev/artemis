import CsrEffortChart, {
    type EffortBucket,
} from '@/components/csr/CsrEffortChart';

export interface HourlyEffortHour {
    /** 0–23. All twenty-four are present, zeros included. */
    hour: number;
    /** Every call the hour carried, counted as the rollup counts a day. */
    total_calls: number;
    /** Every RMO call placed in that hour, however short. */
    calls: number;
    /** The subset that lasted long enough to be a conversation. */
    real: number;
    /** Every order-verification call placed in that hour, however short. */
    verification_calls: number;
    /** The subset of those that lasted long enough to be a conversation. */
    verification_real: number;
}

export interface HourlyEffortTotals {
    total_calls: number;
    calls: number;
    real: number;
    verification_calls: number;
    verification_real: number;
}

export interface HourlyEffortDay {
    /** `YYYY-MM-DD`. Every day in the range is present, quiet ones included. */
    date: string;
    hours: HourlyEffortHour[];
    /** The day's own figures, as the endpoint sends them. */
    totals: HourlyEffortTotals;
}

export interface HourlyEffortResponse {
    range: { from: string; to: string };
    days: HourlyEffortDay[];
    totals: HourlyEffortTotals;
}

/**
 * "9a", "12p" — twenty-four of these sit side by side, so the axis takes the
 * shortest form that still says morning or afternoon.
 */
const hourLabel = (hour: number) =>
    `${hour % 12 === 0 ? 12 : hour % 12}${hour < 12 ? 'a' : 'p'}`;

/** "9:00 AM – 9:59 AM" — the long form, for a tooltip or a table row. */
const hourRange = (hour: number) => {
    const clock = (minutes: string) =>
        `${hour % 12 === 0 ? 12 : hour % 12}:${minutes} ${hour < 12 ? 'AM' : 'PM'}`;

    return `${clock('00')} – ${clock('59')}`;
};

/** "9:00 AM – 9:59 AM · 7 days" — the tooltip says how many it added up. */
const hourTooltip = (hour: number, dayCount: number) =>
    `${hourRange(hour)} · ${dayCount} ${dayCount === 1 ? 'day' : 'days'}`;

/**
 * One round of the clock for the whole range: every day's 9am added into a
 * single 9am.
 *
 * A week of mornings says more about the shape of the working day than any one
 * morning does — one quiet Sunday cannot pull an hour down on its own, and an
 * hour nobody ever works reads as empty across the board rather than as one
 * day's gap. Every day arrives with all 24 of its hours, zeros included, so
 * this is a sum down the columns and nothing else.
 */
function rangeHours(days: HourlyEffortDay[]): HourlyEffortHour[] {
    const clock: HourlyEffortHour[] = Array.from({ length: 24 }, (_, hour) => ({
        hour,
        total_calls: 0,
        calls: 0,
        real: 0,
        verification_calls: 0,
        verification_real: 0,
    }));

    for (const day of days) {
        for (const hour of day.hours) {
            const slot = clock[hour.hour];

            if (!slot) continue;

            slot.total_calls += hour.total_calls;
            slot.calls += hour.calls;
            slot.real += hour.real;
            slot.verification_calls += hour.verification_calls;
            slot.verification_real += hour.verification_real;
        }
    }

    return clock;
}

/**
 * Effort against results, hour by hour.
 *
 * The chart above this one spreads the period's calls across its days; this
 * folds them into one round of the clock, so the shape that shows is the
 * working day itself — when the dialling starts, where it peaks, and the hours
 * where the calls go out but nobody picks up.
 *
 * The whole range at once, rather than a day at a time: the question the hour is
 * asked is when in the day the work lands, and one Tuesday is too small a sample
 * to answer it. The date range at the top of the page is what narrows this — to
 * read a single day, pick that day up there and the clock follows.
 *
 * It is read off the call log rather than the nightly rollup, which keeps no
 * hour: a day the rollup has not reached yet still draws.
 */
export default function CsrHourlyEffortChart({
    data,
    loading,
}: {
    data: HourlyEffortResponse | null;
    loading: boolean;
}) {
    const days = data?.days ?? [];

    const buckets: EffortBucket[] = rangeHours(days).map((hour) => ({
        key: String(hour.hour),
        label: hourLabel(hour.hour),
        tooltip: hourTooltip(hour.hour, days.length),
        // The axis has room for "9a" and no more; a row has room to say it in
        // full, and the heading above already says which days these are.
        rowLabel: hourRange(hour.hour),
        total_calls: hour.total_calls,
        calls: hour.calls,
        real: hour.real,
        verification_calls: hour.verification_calls,
        verification_real: hour.verification_real,
    }));

    return (
        <CsrEffortChart
            eyebrow="Effort against results · Hourly"
            heading="Effort against results, hour by hour"
            rowHeading="Hour"
            note="Every day in the selected range added together — one round of the clock for the whole period."
            buckets={buckets}
            loading={loading}
            // Twenty-four slots against a month's worth of days, so they can be
            // narrower and still leave the labels legible.
            columnWidth={44}
            // A working day rather than a flat block: quiet overnight, a morning
            // climb, a lull at lunch, and off again in the afternoon.
            skeletonBars={[
                4, 3, 2, 2, 3, 8, 22, 48, 70, 86, 92, 74, 46, 68, 84, 90, 78,
                60, 40, 24, 14, 9, 6, 4,
            ]}
        />
    );
}

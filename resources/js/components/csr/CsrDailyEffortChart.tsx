import CsrEffortChart, {
    type EffortBucket,
} from '@/components/csr/CsrEffortChart';

export interface DailyEffortDay {
    /** `YYYY-MM-DD`. Every day in the range is present, zeros included. */
    date: string;
    /** Every RMO call placed that day, however short. */
    calls: number;
    /** The subset that lasted long enough to be a conversation. */
    real: number;
    /** Every order-verification call placed that day, however short. */
    verification_calls: number;
    /** The subset of those that lasted long enough to be a conversation. */
    verification_real: number;
}

export interface DailyEffortResponse {
    range: { from: string; to: string };
    days: DailyEffortDay[];
    totals: {
        calls: number;
        real: number;
        verification_calls: number;
        verification_real: number;
    };
}

/** "Aug 14" — the axis reads days, so the year would be noise. */
const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

/**
 * Effort against results, day by day.
 *
 * One bar slot per day in the range, so a week where the calls held up but the
 * conversations fell away reads as the gap between the pale bar and the solid
 * one widening. The chart itself is CsrEffortChart — this only names the days.
 */
export default function CsrDailyEffortChart({
    data,
    loading,
}: {
    data: DailyEffortResponse | null;
    loading: boolean;
}) {
    const buckets: EffortBucket[] = (data?.days ?? []).map((day) => ({
        key: day.date,
        label: dayLabel(day.date),
        tooltip: dayLabel(day.date),
        calls: day.calls,
        real: day.real,
        verification_calls: day.verification_calls,
        verification_real: day.verification_real,
    }));

    return (
        <CsrEffortChart
            eyebrow="Effort against results · Daily"
            heading="Effort against results, day by day"
            rowHeading="Day"
            buckets={buckets}
            loading={loading}
        />
    );
}

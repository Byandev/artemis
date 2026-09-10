import CsrEffortChart, {
    type EffortBucket,
} from '@/components/csr/CsrEffortChart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, type ReactNode } from 'react';

export interface HourlyEffortHour {
    /** 0–23. All twenty-four are present, zeros included. */
    hour: number;
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
    calls: number;
    real: number;
    verification_calls: number;
    verification_real: number;
}

export interface HourlyEffortDay {
    /** `YYYY-MM-DD`. Every day in the range is present, quiet ones included. */
    date: string;
    hours: HourlyEffortHour[];
    /** The day's own figures — what the picker reads to mark a quiet day. */
    totals: HourlyEffortTotals;
}

export interface HourlyEffortResponse {
    range: { from: string; to: string };
    days: HourlyEffortDay[];
    totals: HourlyEffortTotals;
}

/** Every call the day carried, both kinds — was there anything to draw. */
const dayCalls = (day: HourlyEffortDay) =>
    day.totals.calls + day.totals.verification_calls;

/** "Fri, Aug 14" — the weekday earns its place when the point is comparing days. */
const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });

/**
 * "9a", "12p" — twenty-four of these sit side by side, so the axis takes the
 * shortest form that still says morning or afternoon.
 */
const hourLabel = (hour: number) =>
    `${hour % 12 === 0 ? 12 : hour % 12}${hour < 12 ? 'a' : 'p'}`;

/** "Fri, Aug 14 · 9:00 AM – 9:59 AM" — the tooltip has room to say it in full. */
const hourTooltip = (date: string, hour: number) => {
    const clock = (h: number, minutes: string) =>
        `${h % 12 === 0 ? 12 : h % 12}:${minutes} ${h < 12 ? 'AM' : 'PM'}`;

    return `${dayLabel(date)} · ${clock(hour, '00')} – ${clock(hour, '59')}`;
};

/**
 * The day the picker opens on: the most recent one that had any calls.
 *
 * The last day of the range would be the obvious pick, but a range that ends
 * today opens at breakfast on a day nobody has worked yet — an empty chart that
 * reads as a broken one. Falling back to the last day keeps the picker on a
 * real date when the whole range is quiet.
 */
function defaultDate(days: HourlyEffortDay[]): string | null {
    if (days.length === 0) return null;

    const worked = [...days].reverse().find((day) => dayCalls(day) > 0);

    return (worked ?? days[days.length - 1]).date;
}

/**
 * Effort against results, hour by hour.
 *
 * The chart above this one spreads the period's calls across its days; this
 * opens one of those days up into its own round of the clock, so the shape that
 * shows is the working day itself — when the dialling starts, where it peaks,
 * and the hours where the calls go out but nobody picks up.
 *
 * One day at a time, rather than the range flattened into a single
 * twenty-four hour profile: an hour of a Tuesday and the same hour of a
 * Saturday are different things, and an average of the two hides the day that
 * went wrong. The whole range arrives in one response, so stepping between days
 * costs no request.
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
    const [picked, setPicked] = useState<string | null>(null);

    const days = data?.days ?? [];

    // A pick is kept only while the range still holds it — changing the dates
    // drops a day that is no longer on offer and falls back to the default,
    // rather than leaving the picker naming a day the chart cannot draw.
    const active =
        picked && days.some((day) => day.date === picked)
            ? picked
            : defaultDate(days);

    const index = days.findIndex((day) => day.date === active);
    const day = index >= 0 ? days[index] : null;

    const buckets: EffortBucket[] = day
        ? day.hours.map((hour) => ({
              key: String(hour.hour),
              label: hourLabel(hour.hour),
              tooltip: hourTooltip(day.date, hour.hour),
              calls: hour.calls,
              real: hour.real,
              verification_calls: hour.verification_calls,
              verification_real: hour.verification_real,
          }))
        : [];

    return (
        <CsrEffortChart
            eyebrow="Effort against results · Hourly"
            heading="Effort against results, hour by hour"
            note="One day at a time — step through the range with the picker."
            emptySubject={
                day ? `on ${dayLabel(day.date)}` : 'in the selected period'
            }
            control={
                <DayPicker
                    days={days}
                    index={index}
                    loading={loading}
                    onPick={setPicked}
                />
            }
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

/**
 * Which day of the range the clock belongs to.
 *
 * Two arrows for the day either side, and the date itself is a dropdown for
 * jumping across a long range without clicking through it. Days nobody worked
 * are listed rather than skipped — that a Sunday is empty is worth seeing — and
 * are marked as such, so the list says where the work was before it is opened.
 */
function DayPicker({
    days,
    index,
    loading,
    onPick,
}: {
    days: HourlyEffortDay[];
    index: number;
    loading: boolean;
    onPick: (date: string) => void;
}) {
    const step = (by: number) => onPick(days[index + by].date);

    const disabled = loading || days.length === 0 || index < 0;

    return (
        <div className="flex items-center gap-1">
            <StepButton
                label="Previous day"
                disabled={disabled || index <= 0}
                onClick={() => step(-1)}
            >
                <ChevronLeft className="h-3.5 w-3.5" />
            </StepButton>

            <Select
                value={index >= 0 ? days[index].date : undefined}
                onValueChange={onPick}
                disabled={disabled}
            >
                <SelectTrigger aria-label="Day" className="w-[150px]">
                    <SelectValue placeholder="Pick a day" />
                </SelectTrigger>
                <SelectContent>
                    {days.map((day) => (
                        <SelectItem key={day.date} value={day.date}>
                            {dayLabel(day.date)}
                            {dayCalls(day) === 0 && (
                                <span className="text-gray-400 dark:text-gray-500">
                                    · no calls
                                </span>
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <StepButton
                label="Next day"
                disabled={disabled || index >= days.length - 1}
                onClick={() => step(1)}
            >
                <ChevronRight className="h-3.5 w-3.5" />
            </StepButton>
        </div>
    );
}

function StepButton({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            className="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border border-black/6 bg-stone-100 text-gray-600 transition-colors hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-white dark:disabled:hover:text-gray-300"
        >
            {children}
        </button>
    );
}

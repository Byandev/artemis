import { Skeleton } from '@/components/ui/skeleton';
import {
    emptyFootnote,
    type WelleStatContext,
} from '@/components/welle/WelleStatCards';
import { useMemo } from 'react';

/** One day Welle has a record of. */
interface CalendarDay {
    /** `YYYY-MM-DD`. */
    date: string;
    /** 0–3 of the pillars ticked that day. */
    pillars_completed: number;
}

/**
 * The calendar endpoint's answer.
 * See WelleStatsController::calendar.
 */
export interface EscCalendarStat extends WelleStatContext {
    /** Days in date order. Days still to come simply are not here. */
    days: CalendarDay[];
}

/**
 * How complete a day was, in colour — lighter for one pillar, darkest green
 * for all three.
 *
 * Index is the pillar count, so a day reads straight off the payload. The
 * classes are written out rather than built from the count: Tailwind only
 * ships a class it can see in the source.
 */
const LEVELS = [
    {
        label: 'nothing ticked',
        cell: 'bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500',
        swatch: 'bg-stone-200 dark:bg-zinc-700',
    },
    {
        label: '1 pillar',
        cell: 'bg-brand-200 text-brand-900 dark:bg-brand-900 dark:text-brand-100',
        swatch: 'bg-brand-200 dark:bg-brand-900',
    },
    {
        label: '2 pillars',
        cell: 'bg-brand-400 text-brand-900 dark:bg-brand-700 dark:text-brand-50',
        swatch: 'bg-brand-400 dark:bg-brand-700',
    },
    {
        label: 'all 3 — an ESC day',
        cell: 'bg-brand-600 text-white dark:bg-brand-500 dark:text-brand-950',
        swatch: 'bg-brand-600 dark:bg-brand-500',
    },
];

/** A day of the month Welle has no row for — still to come, or never synced. */
const UNTRACKED =
    'text-gray-300 ring-1 ring-black/5 ring-inset dark:text-gray-600 dark:ring-white/8';

/** Sunday first, as the month grid is laid out. */
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * The month, day by day, each day coloured by how many pillars it carried.
 *
 * A grid rather than a list because the point is the shape of the month —
 * which weeks held together and which fell away — and a run of pale days says
 * that at a glance in a way thirty rows cannot.
 *
 * The month is drawn from the `YYYY-MM` the endpoint answers with rather than
 * from today's date, so asking for an earlier month draws that month's shape
 * and not this one's.
 */
export function EscCalendar({
    stat,
    loading,
}: {
    stat: EscCalendarStat | null;
    loading: boolean;
}) {
    const grid = useMemo(() => buildGrid(stat), [stat]);
    const empty = stat ? emptyFootnote(stat) : null;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4 flex items-center justify-between gap-2">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    ESC Calendar
                </span>
                {loading || !stat ? (
                    <Skeleton className="block h-[13px] w-24 rounded" />
                ) : (
                    <span className="text-[11px] text-gray-400 dark:text-gray-500">
                        {stat.month_label}
                    </span>
                )}
            </div>

            {/* Full width, but a day cell keeps its height: it has only a
                number to carry, so the grid widens with the card without the
                cells growing with it. */}
            <div className="w-full">
                <div className="mb-1 grid grid-cols-7 gap-1">
                    {WEEKDAYS.map((day) => (
                        <span
                            key={day}
                            className="text-center text-[10px] font-medium text-gray-400 dark:text-gray-500"
                        >
                            {day.charAt(0)}
                        </span>
                    ))}
                </div>

                {loading || !stat ? (
                    <div className="grid grid-cols-7 gap-1">
                        {Array.from({ length: 35 }, (_, cell) => (
                            <Skeleton
                                key={cell}
                                className="block h-8 w-full rounded-[5px]"
                            />
                        ))}
                    </div>
                ) : (
                    <div className="grid grid-cols-7 gap-1">
                        {grid.map((day, cell) =>
                            day === null ? (
                                // Padding before the first of the month, so the
                                // first day lands on its own weekday.
                                <span key={cell} />
                            ) : (
                                <span
                                    key={cell}
                                    title={dayTitle(stat.month, day)}
                                    className={`flex h-8 items-center justify-center rounded-[5px] text-[10px] font-medium tabular-nums ${
                                        day.level === null
                                            ? UNTRACKED
                                            : LEVELS[day.level].cell
                                    }`}
                                >
                                    {day.day}
                                </span>
                            ),
                        )}
                    </div>
                )}
            </div>

            {empty ? (
                // Nothing synced: the same advice the cards give, in place of a
                // legend for colours none of these days carry.
                <p className="mt-3.5 text-center text-[11px] text-gray-400 dark:text-gray-500">
                    {empty}
                </p>
            ) : (
                <div className="mt-3.5 flex items-center justify-end gap-1.5">
                    <span className="mr-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                        Pillars
                    </span>
                    {LEVELS.map((level, count) => (
                        <span
                            key={level.label}
                            title={level.label}
                            className="flex items-center gap-1"
                        >
                            <span
                                className={`h-2.5 w-2.5 rounded-[3px] ${level.swatch}`}
                            />
                            <span className="text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
                                {count}
                            </span>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

/** A day of the month grid: its number, and how complete it was. */
interface GridDay {
    day: number;
    /** 0–3, or null for a day Welle has no row for. */
    level: number | null;
}

/**
 * The month laid out as cells — leading blanks, then every day of it.
 *
 * Built from the month the endpoint named, with the days it sent looked up by
 * date, so a day missing from the payload is drawn as untracked rather than as
 * a day with nothing done.
 */
function buildGrid(stat: EscCalendarStat | null): (GridDay | null)[] {
    if (!stat) return [];

    const [year, month] = stat.month.split('-').map(Number);
    const first = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();

    const levels = new Map(
        stat.days.map((day) => [day.date, day.pillars_completed]),
    );

    const cells: (GridDay | null)[] = Array.from(
        { length: first.getDay() },
        () => null,
    );

    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${stat.month}-${String(day).padStart(2, '0')}`;

        cells.push({ day, level: levels.get(date) ?? null });
    }

    return cells;
}

/** What a day says on hover — its date, and what was done on it. */
function dayTitle(month: string, { day, level }: GridDay): string {
    const date = `${month}-${String(day).padStart(2, '0')}`;

    return level === null
        ? `${date} · not tracked`
        : `${date} · ${LEVELS[level].label}`;
}

import moment from 'moment';
import { useMemo } from 'react';
import { CalendarData, CalendarDay } from './types';

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Distinct, legible accents — one per user, shared by the legend and cells. */
const PALETTE = [
    {
        dot: 'bg-emerald-500',
        pill: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    {
        dot: 'bg-blue-500',
        pill: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
    {
        dot: 'bg-violet-500',
        pill: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    },
    {
        dot: 'bg-amber-500',
        pill: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    {
        dot: 'bg-rose-500',
        pill: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    },
    {
        dot: 'bg-cyan-500',
        pill: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    },
    {
        dot: 'bg-indigo-500',
        pill: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    },
    {
        dot: 'bg-pink-500',
        pill: 'bg-pink-500/10 text-pink-700 dark:text-pink-300',
    },
    {
        dot: 'bg-teal-500',
        pill: 'bg-teal-500/10 text-teal-700 dark:text-teal-300',
    },
    {
        dot: 'bg-orange-500',
        pill: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
    },
];

const MAX_PILLS = 4;

interface LegendEntry {
    creator_id: number;
    name: string;
    total: number;
}

/**
 * A real month calendar of creative-creation activity. A per-user colour
 * legend (sorted by output) lets you scan who created what across the month;
 * each day lists its creators with matching colours and counts.
 */
export default function CreativesCalendar({ data }: { data: CalendarData }) {
    const byDate = useMemo(() => {
        const map: Record<string, CalendarDay> = {};
        data.days.forEach((day) => {
            map[day.date] = day;
        });
        return map;
    }, [data.days]);

    // Per-user totals over the whole period → the legend, sorted by output.
    const legend = useMemo<LegendEntry[]>(() => {
        const totals = new Map<number, LegendEntry>();
        data.days.forEach((day) =>
            day.creators.forEach((creator) => {
                const current = totals.get(creator.creator_id) ?? {
                    creator_id: creator.creator_id,
                    name: creator.name,
                    total: 0,
                };
                current.total += creator.count;
                totals.set(creator.creator_id, current);
            }),
        );
        return [...totals.values()].sort((a, b) => b.total - a.total);
    }, [data.days]);

    // Stable colour per user (by legend order).
    const colorOf = useMemo(() => {
        const map = new Map<number, (typeof PALETTE)[number]>();
        legend.forEach((entry, index) =>
            map.set(entry.creator_id, PALETTE[index % PALETTE.length]),
        );
        return map;
    }, [legend]);

    const grandTotal = useMemo(
        () => legend.reduce((sum, entry) => sum + entry.total, 0),
        [legend],
    );

    const months = useMemo(() => {
        const result: string[] = [];
        const cursor = moment(data.from).startOf('month');
        const end = moment(data.to).startOf('month');
        while (cursor.isSameOrBefore(end)) {
            result.push(cursor.format('YYYY-MM'));
            cursor.add(1, 'month');
        }
        return result;
    }, [data.from, data.to]);

    const today = moment().format('YYYY-MM-DD');

    if (data.days.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-14 text-center">
                <div className="mb-2 text-4xl opacity-50">🗓️</div>
                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    No creatives created in this period
                </p>
                <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                    Try widening the date range.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Per-user legend */}
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="mr-1 text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {grandTotal} total ·
                </span>
                {legend.map((entry) => {
                    const color = colorOf.get(entry.creator_id) ?? PALETTE[0];
                    return (
                        <span
                            key={entry.creator_id}
                            className="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-white py-1 pr-2 pl-1.5 text-[11px] font-medium text-gray-600 shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <span
                                className={`h-2 w-2 rounded-full ${color.dot}`}
                            />
                            <span className="max-w-32 truncate">
                                {entry.name}
                            </span>
                            <span className="font-mono text-gray-400 dark:text-gray-500">
                                {entry.total}
                            </span>
                        </span>
                    );
                })}
            </div>

            {months.map((month) => {
                const start = moment(`${month}-01`);
                const daysInMonth = start.daysInMonth();
                const leading = start.day(); // 0 = Sunday
                const cells: (number | null)[] = [
                    ...Array<null>(leading).fill(null),
                    ...Array.from({ length: daysInMonth }, (_, i) => i + 1),
                ];
                // Pad the tail so the grid always ends on a full week.
                while (cells.length % 7 !== 0) cells.push(null);

                return (
                    <div key={month}>
                        <h4 className="mb-2 text-[13px] font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                            {start.format('MMMM YYYY')}
                        </h4>

                        {/* Calendar grid (gap-px shows through as grid lines) */}
                        <div className="overflow-hidden rounded-[12px] border border-black/8 dark:border-white/8">
                            <div className="grid grid-cols-7 bg-stone-50 dark:bg-zinc-800/50">
                                {WEEKDAYS.map((weekday) => (
                                    <div
                                        key={weekday}
                                        className="border-b border-black/6 py-2 text-center font-mono text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:border-white/6 dark:text-gray-500"
                                    >
                                        {weekday}
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-7 gap-px bg-black/6 dark:bg-white/6">
                                {cells.map((day, index) => {
                                    if (day === null) {
                                        return (
                                            <div
                                                key={`pad-${index}`}
                                                className="min-h-[7rem] bg-stone-50/60 dark:bg-zinc-900/40"
                                            />
                                        );
                                    }

                                    const date = `${month}-${String(day).padStart(2, '0')}`;
                                    const entry = byDate[date];
                                    const isToday = date === today;
                                    const creators = entry?.creators ?? [];

                                    return (
                                        <div
                                            key={date}
                                            className="flex min-h-[7rem] flex-col gap-1 bg-white p-1.5 dark:bg-zinc-900"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span
                                                    className={[
                                                        'flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold',
                                                        isToday
                                                            ? 'bg-emerald-600 text-white'
                                                            : 'text-gray-500 dark:text-gray-400',
                                                    ].join(' ')}
                                                >
                                                    {day}
                                                </span>
                                                {entry && entry.total > 0 && (
                                                    <span className="text-[10px] font-medium text-gray-400 dark:text-gray-500">
                                                        {entry.total}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="flex flex-col gap-1">
                                                {creators
                                                    .slice(0, MAX_PILLS)
                                                    .map((creator) => {
                                                        const color =
                                                            colorOf.get(
                                                                creator.creator_id,
                                                            ) ?? PALETTE[0];
                                                        return (
                                                            <span
                                                                key={
                                                                    creator.creator_id
                                                                }
                                                                title={`${creator.name}: ${creator.count}`}
                                                                className={`flex items-center gap-1 rounded-[6px] px-1.5 py-0.5 text-[10px] font-medium ${color.pill}`}
                                                            >
                                                                <span
                                                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${color.dot}`}
                                                                />
                                                                <span className="truncate">
                                                                    {
                                                                        creator.name
                                                                    }
                                                                </span>
                                                                <span className="ml-auto font-mono opacity-70">
                                                                    {
                                                                        creator.count
                                                                    }
                                                                </span>
                                                            </span>
                                                        );
                                                    })}
                                                {creators.length >
                                                    MAX_PILLS && (
                                                    <span
                                                        title={creators
                                                            .slice(MAX_PILLS)
                                                            .map(
                                                                (c) =>
                                                                    `${c.name}: ${c.count}`,
                                                            )
                                                            .join('\n')}
                                                        className="px-1.5 text-[10px] font-medium text-gray-400 dark:text-gray-500"
                                                    >
                                                        +
                                                        {creators.length -
                                                            MAX_PILLS}{' '}
                                                        more
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

import { Skeleton } from '@/components/ui/skeleton';

export interface DailyCallOutcome {
    /** `YYYY-MM-DD`. Every day in the range is present, quiet ones included. */
    date: string;
    /** Every RMO call placed that day, however short. */
    calls: number;
    /** The attempts the phone never joined — rang out, or the line was busy. */
    no_answer: number;
    /** Somebody picked up, however briefly. */
    answered: number;
    /** The answered calls that lasted long enough to be a conversation. */
    conversations: number;
    /** Conversations over every attempt. Null on a day with no calls. */
    hit_rate: number | null;
}

export interface DailyCallOutcomesResponse {
    range: { from: string; to: string };
    days: DailyCallOutcome[];
    totals: Omit<DailyCallOutcome, 'date'>;
}

/** "Aug 14" — the rows are days, so the year would be noise. */
const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

/**
 * Where each day's calls ended up.
 *
 * The effort chart above answers "where is the gap widest"; this answers "by
 * how much, on which day" — and it is what a screen reader, a copy-paste and a
 * colourblind reader get from the section.
 *
 * The three counts do not add up, and that is the point: calls placed splits
 * into never answered and answered, and conversations is a cut of the answered
 * ones. What is left over is the pick-up-and-hang-up.
 */
export default function CsrDailyCallOutcomesTable({
    data,
    loading,
}: {
    data: DailyCallOutcomesResponse | null;
    loading: boolean;
}) {
    const days = data?.days ?? [];

    if (loading) return <OutcomesSkeleton />;
    // The chart above already says the period was quiet; an empty table under
    // it would only say it again.
    if (!days.some((day) => day.calls > 0)) return null;

    return (
        // Long ranges scroll inside the card with the header pinned, so the
        // columns stay named however far down the month you read.
        <div className="mt-3 mb-4 max-h-96 overflow-y-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <table className="w-full border-collapse">
                <thead className="sticky top-0 bg-white dark:bg-zinc-900">
                    <tr className="border-b border-black/6 dark:border-white/6">
                        <Th align="left">Day</Th>
                        <Th>Calls</Th>
                        <Th>No answer</Th>
                        <Th>Conversations</Th>
                        <Th>Hit rate</Th>
                    </tr>
                </thead>
                <tbody>
                    {days.map((day) => (
                        <tr
                            key={day.date}
                            className="border-b border-black/5 last:border-0 dark:border-white/5"
                        >
                            <td className="px-4 py-3 text-left font-mono text-[12px] whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {dayLabel(day.date)}
                            </td>
                            <Td>{day.calls.toLocaleString()}</Td>
                            {/* The attempts that bought nothing — recessive, so
                                it reads as the shortfall rather than as another
                                headline figure. */}
                            <Td muted>{day.no_answer.toLocaleString()}</Td>
                            <Td>{day.conversations.toLocaleString()}</Td>
                            <td className="px-4 py-3 text-right font-mono text-[12px] font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                {day.hit_rate === null ? (
                                    <span className="font-normal text-gray-300 dark:text-gray-600">
                                        —
                                    </span>
                                ) : (
                                    <>
                                        {day.hit_rate.toFixed(1)}
                                        <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                            %
                                        </span>
                                    </>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Th({
    children,
    align = 'right',
}: {
    children: React.ReactNode;
    align?: 'left' | 'right';
}) {
    return (
        <th
            className={`px-4 py-3 font-mono text-[10px] font-normal tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500 ${
                align === 'left' ? 'text-left' : 'text-right'
            }`}
        >
            {children}
        </th>
    );
}

function Td({
    children,
    muted = false,
}: {
    children: React.ReactNode;
    muted?: boolean;
}) {
    return (
        <td
            className={`px-4 py-3 text-right font-mono text-[12px] tabular-nums ${
                muted
                    ? 'text-gray-400 dark:text-gray-500'
                    : 'text-gray-700 dark:text-gray-200'
            }`}
        >
            {children}
        </td>
    );
}

function OutcomesSkeleton() {
    return (
        <div className="mt-3 mb-4 rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            {Array.from({ length: 7 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-2.5">
                    <Skeleton className="h-3 w-16" />
                    <Skeleton className="ml-auto h-3 w-10" />
                    <Skeleton className="h-3 w-10" />
                    <Skeleton className="h-3 w-10" />
                    <Skeleton className="h-3 w-12" />
                </div>
            ))}
        </div>
    );
}

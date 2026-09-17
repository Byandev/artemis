import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    emptyFootnote,
    type Pillar,
    type WelleStatContext,
} from '@/components/welle/WelleStatCards';
import { Check } from 'lucide-react';

/** One day of the log: the date, and the three pillars as they were ticked. */
interface DailyLogDay extends Record<Pillar, boolean> {
    /** `YYYY-MM-DD`. */
    date: string;
    /** All three done. */
    is_esc: boolean;
}

/**
 * The daily log endpoint's answer.
 * See WelleStatsController::dailyLog.
 */
export interface DailyLogStat extends WelleStatContext {
    /** Days in date order. Days still to come simply are not here. */
    days: DailyLogDay[];
}

/** The columns, in the order Welle lists the pillars. */
const PILLAR_ORDER: Pillar[] = ['movement', 'meditation', 'learning'];

/**
 * A header cell, stuck to the top of the scrolling body.
 *
 * Sticky on the cells rather than on the row: a `thead` cannot be a sticky
 * container everywhere, and a background on each cell is what keeps the rows
 * from showing through as they pass under it.
 */
const HEAD =
    'sticky top-0 z-10 border-b border-black/6 bg-white py-2.5 text-[11px] font-medium text-gray-400 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-500';

/**
 * Day by day — a row per day Welle has a record of, a tick per pillar done.
 *
 * The calendar says how complete a day was; this says which pillar it was that
 * gave way, which is the thing to act on. A ticked box rather than a bare
 * check, and an empty one rather than a blank cell, so a missed pillar reads as
 * a deliberate no rather than as data that never arrived.
 */
export function DailyLogTable({
    stat,
    loading,
}: {
    stat: DailyLogStat | null;
    loading: boolean;
}) {
    const empty = stat ? emptyFootnote(stat) : null;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between gap-2 p-[18px] pb-3">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    Day by Day
                </span>
                {loading || !stat ? (
                    <Skeleton className="block h-[13px] w-24 rounded" />
                ) : (
                    <span className="text-[11px] text-gray-400 dark:text-gray-500">
                        {stat.month_label}
                    </span>
                )}
            </div>

            {empty ? (
                // Nothing synced: the same advice the cards give, rather than a
                // header over an empty table.
                <p className="px-[18px] pt-2 pb-8 text-center text-[12px] text-gray-400 dark:text-gray-500">
                    {empty}
                </p>
            ) : (
                <div className="max-h-[420px] overflow-auto rounded-b-[14px]">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead
                                    className={`${HEAD} pr-4 pl-[18px] text-left`}
                                >
                                    Day
                                </TableHead>
                                {PILLAR_ORDER.map((pillar) => (
                                    <TableHead
                                        key={pillar}
                                        className={`${HEAD} px-4 text-center capitalize`}
                                    >
                                        {pillar}
                                    </TableHead>
                                ))}
                                <TableHead
                                    className={`${HEAD} pr-[18px] pl-4 text-right`}
                                >
                                    ESC
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {loading || !stat
                                ? Array.from({ length: 6 }, (_, row) => (
                                      <TableRow key={row}>
                                          <TableCell className="pl-[18px]">
                                              <Skeleton className="block h-[13px] w-24 rounded" />
                                          </TableCell>
                                          {PILLAR_ORDER.map((pillar) => (
                                              <TableCell key={pillar}>
                                                  <Skeleton className="mx-auto block h-4 w-4 rounded" />
                                              </TableCell>
                                          ))}
                                          <TableCell className="pr-[18px]">
                                              <Skeleton className="ml-auto block h-4 w-4 rounded" />
                                          </TableCell>
                                      </TableRow>
                                  ))
                                : stat.days.map((day) => (
                                      <TableRow key={day.date}>
                                          <TableCell className="pl-[18px] text-[12px] whitespace-nowrap text-gray-600 dark:text-gray-300">
                                              {dayLabel(day.date)}
                                          </TableCell>

                                          {PILLAR_ORDER.map((pillar) => (
                                              <TableCell
                                                  key={pillar}
                                                  className="text-center"
                                              >
                                                  <Tick done={day[pillar]} />
                                              </TableCell>
                                          ))}

                                          <TableCell className="pr-[18px] text-right">
                                              <Tick done={day.is_esc} />
                                          </TableCell>
                                      </TableRow>
                                  ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

/**
 * A pillar done, or plainly not — a checklist box, ticked green.
 *
 * The same box either way rather than a tick against nothing: an empty box is
 * a day that could have had this and did not, which is the comparison the
 * column is there to make.
 */
function Tick({ done }: { done: boolean }) {
    return done ? (
        <span className="inline-flex h-4 w-4 items-center justify-center rounded-[4px] bg-brand-600 align-middle dark:bg-brand-500">
            <Check
                className="h-3 w-3 text-white dark:text-brand-950"
                strokeWidth={3}
            />
        </span>
    ) : (
        <span className="inline-block h-4 w-4 rounded-[4px] border border-black/10 bg-stone-50 align-middle dark:border-white/10 dark:bg-zinc-800" />
    );
}

/** "Mon 1" — the weekday and the day of the month, which is all a row needs. */
function dayLabel(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    const weekday = new Date(year, month - 1, day).toLocaleDateString(
        undefined,
        { weekday: 'short' },
    );

    return `${weekday} ${day}`;
}

import { cn } from '@/lib/utils';
import { CalendarDays } from 'lucide-react';
import { shortDate } from './formatters';
import type { CsrScheduleEntry, PancakeAccount } from './types';

interface Props {
    schedules: CsrScheduleEntry[];
    pancakeAccounts: PancakeAccount[];
}

export default function CsrScheduleList({ schedules, pancakeAccounts }: Props) {
    if (schedules.length === 0) {
        return (
            <div className="flex h-32 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No schedules for this period.
            </div>
        );
    }

    return (
        <div>
            {schedules.map((s) => {
                const isMine = pancakeAccounts.some((a) => a.id === s.pancake_user_id);
                return (
                    <div
                        key={s.id}
                        className={cn(
                            'flex items-center gap-4 border-t border-black/4 px-5 py-3 dark:border-white/4',
                            isMine && 'bg-emerald-50/60 dark:bg-emerald-500/5',
                        )}
                    >
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-zinc-800">
                            <CalendarDays className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                {s.name}
                                {isMine && (
                                    <span className="ml-1.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        You
                                    </span>
                                )}
                            </p>
                            {s.notes && (
                                <p className="text-[11px] text-gray-400 dark:text-gray-500">{s.notes}</p>
                            )}
                        </div>
                        <div className="text-right">
                            <p className="text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                {shortDate(s.date)}
                            </p>
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {s.shift_start} – {s.shift_end}
                            </p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

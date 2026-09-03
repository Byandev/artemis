import { cn } from '@/lib/utils';
import MemberAvatar from './member-avatar';
import type { MemberProgress } from './types';

/**
 * The header above the checklist: who it belongs to, how much is left, and a
 * bar that turns green only when nothing is.
 */
export default function MemberSummary({
    progress,
}: {
    progress: MemberProgress;
}) {
    const { member, done, total, percent, isComplete } = progress;
    const remaining = total - done;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center gap-3">
                <MemberAvatar id={member.id} name={member.name} size="lg" />

                <div className="min-w-0 flex-1">
                    <p className="truncate text-[15px] font-semibold text-gray-800 dark:text-gray-100">
                        {member.name}
                    </p>
                    <p className="font-mono! text-[11px]! text-gray-400 dark:text-gray-500">
                        {done} of {total} deliverables done · {percent}%
                    </p>
                </div>

                <span
                    className={cn(
                        'shrink-0 rounded-full px-2.5 py-1 font-mono! text-[10px]! font-medium',
                        isComplete
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                    )}
                >
                    {isComplete ? 'All done' : `${remaining} remaining`}
                </span>
            </div>

            <div
                className="mt-3 h-2 w-full overflow-hidden rounded-full bg-black/5 dark:bg-white/10"
                role="progressbar"
                aria-valuenow={percent}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={`${member.name}: ${percent}% of deliverables done`}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width] duration-300',
                        isComplete ? 'bg-emerald-500' : 'bg-amber-400',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

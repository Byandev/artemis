import { cn } from '@/lib/utils';
import MemberAvatar from './member-avatar';
import ProgressRing from './progress-ring';
import type { MemberProgress, TrackerMember } from './types';

/**
 * The members down the left: who is on the board, how far each has got, and
 * which one the checklist beside it is showing.
 */
export default function MemberRoster({
    members,
    progress,
    selectedId,
    onSelect,
}: {
    members: TrackerMember[];
    progress: ReadonlyMap<number, MemberProgress>;
    selectedId: number | null;
    onSelect: (memberId: number) => void;
}) {
    const complete = members.filter(
        (member) => progress.get(member.id)?.isComplete,
    ).length;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
            <p className="px-2 py-1 font-mono! text-[10px]! font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                Interns · {complete}/{members.length} complete
            </p>

            <ul className="mt-1 flex flex-col gap-0.5">
                {members.map((member) => {
                    const stats = progress.get(member.id);
                    const isSelected = member.id === selectedId;

                    return (
                        <li key={member.id}>
                            <button
                                type="button"
                                onClick={() => onSelect(member.id)}
                                aria-current={isSelected ? 'true' : undefined}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-[10px] px-2 py-2 text-left transition-colors',
                                    isSelected
                                        ? 'bg-emerald-500/8 dark:bg-emerald-500/10'
                                        : 'hover:bg-black/2 dark:hover:bg-white/2',
                                )}
                            >
                                <MemberAvatar
                                    id={member.id}
                                    name={member.name}
                                />

                                <span className="min-w-0 flex-1">
                                    <span
                                        className={cn(
                                            'block truncate text-[13px] font-medium',
                                            isSelected
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-gray-700 dark:text-gray-200',
                                        )}
                                    >
                                        {member.name}
                                    </span>
                                    <span className="block font-mono! text-[10px]! text-gray-400 dark:text-gray-500">
                                        {stats?.done ?? 0} of{' '}
                                        {stats?.total ?? 0} done
                                    </span>
                                </span>

                                <ProgressRing percent={stats?.percent ?? 0} />
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

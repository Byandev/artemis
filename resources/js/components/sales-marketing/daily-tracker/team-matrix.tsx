import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import type { CategoryTheme } from './category-theme';
import { FALLBACK_THEME } from './category-theme';
import MemberAvatar from './member-avatar';
import type {
    MemberProgress,
    TrackerCategory,
    TrackerItem,
    TrackerMember,
} from './types';

const HEAD_CELL =
    'border-b border-black/6 bg-stone-50 px-2 py-2.5 text-center align-middle dark:border-white/6 dark:bg-white/2';

/**
 * Every member against every deliverable — the view for spotting the row or the
 * column that is lagging, rather than one person's day.
 *
 * Cells are the same toggles as the checklist: what a viewer may tick here is
 * what they may tick there.
 */
export default function TeamMatrix({
    members,
    categories,
    themes,
    progress,
    isDone,
    isPending,
    canTick,
    onToggle,
}: {
    members: TrackerMember[];
    categories: TrackerCategory[];
    themes: ReadonlyMap<string, CategoryTheme>;
    progress: ReadonlyMap<number, MemberProgress>;
    isDone: (memberId: number, itemId: number) => boolean;
    isPending: (memberId: number, itemId: number) => boolean;
    canTick: (memberId: number) => boolean;
    onToggle: (memberId: number, item: TrackerItem) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <table className="w-full border-collapse">
                <caption className="sr-only">
                    Deliverables by member for the selected day
                </caption>

                <thead>
                    <tr>
                        <th
                            scope="col"
                            className={cn(
                                HEAD_CELL,
                                'sticky left-0 z-10 min-w-[18rem] text-left font-mono! text-[10px]! font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500',
                            )}
                        >
                            Deliverable
                        </th>
                        {members.map((member) => (
                            <th
                                key={member.id}
                                scope="col"
                                className={cn(HEAD_CELL, 'w-16')}
                            >
                                <span className="flex flex-col items-center gap-1">
                                    <MemberAvatar
                                        id={member.id}
                                        name={member.name}
                                        size="sm"
                                    />
                                    <span className="font-mono! text-[9px]! text-gray-400 dark:text-gray-500">
                                        {progress.get(member.id)?.percent ?? 0}%
                                    </span>
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>

                {categories.map((category) => {
                    const theme = themes.get(category.name) ?? FALLBACK_THEME;

                    return (
                        <tbody key={category.name}>
                            <tr>
                                <th
                                    scope="colgroup"
                                    colSpan={members.length + 1}
                                    className={cn(
                                        'px-4 py-1.5 text-left font-mono! text-[10px]! font-medium tracking-[0.08em]',
                                        theme.band,
                                        theme.heading,
                                    )}
                                >
                                    {category.name}
                                </th>
                            </tr>

                            {category.items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-b border-black/4 last:border-0 dark:border-white/4"
                                >
                                    <th
                                        scope="row"
                                        className="sticky left-0 z-10 max-w-md bg-white px-4 py-2.5 text-left text-[12px] font-normal text-gray-600 dark:bg-zinc-900 dark:text-gray-300"
                                    >
                                        {item.label}
                                    </th>

                                    {members.map((member) => {
                                        const done = isDone(member.id, item.id);
                                        const locked = !canTick(member.id);

                                        return (
                                            <td
                                                key={member.id}
                                                className="px-2 py-2.5 text-center"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={locked}
                                                    aria-pressed={done}
                                                    aria-label={`${member.name}: ${item.label}`}
                                                    onClick={() =>
                                                        onToggle(
                                                            member.id,
                                                            item,
                                                        )
                                                    }
                                                    className={cn(
                                                        'inline-flex size-6 items-center justify-center rounded-[7px] border transition-colors',
                                                        done
                                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                                            : 'border-black/10 bg-transparent text-transparent dark:border-white/15',
                                                        !locked &&
                                                            !done &&
                                                            'hover:border-emerald-400 hover:bg-emerald-500/10',
                                                        locked &&
                                                            'cursor-not-allowed opacity-60',
                                                        isPending(
                                                            member.id,
                                                            item.id,
                                                        ) && 'opacity-50',
                                                    )}
                                                >
                                                    <Check className="size-3.5" />
                                                </button>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    );
                })}

                <tfoot>
                    <tr className="border-t border-black/6 dark:border-white/6">
                        <th
                            scope="row"
                            className="sticky left-0 z-10 bg-stone-50 px-4 py-2.5 text-left font-mono! text-[10px]! font-medium tracking-[0.08em] text-gray-400 uppercase dark:bg-white/2 dark:text-gray-500"
                        >
                            Done
                        </th>
                        {members.map((member) => {
                            const stats = progress.get(member.id);

                            return (
                                <td
                                    key={member.id}
                                    className="bg-stone-50 px-2 py-2.5 text-center font-mono! text-[10px]! text-gray-500 dark:bg-white/2 dark:text-gray-400"
                                >
                                    {stats?.done ?? 0}/{stats?.total ?? 0}
                                </td>
                            );
                        })}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

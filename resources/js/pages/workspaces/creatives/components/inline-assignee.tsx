import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { Check, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Creative, Reviewer } from '../types';
import { InitialAvatar } from './atoms';

/**
 * Inline, ClickUp-style reviewer assignment for the creatives table. Shows the
 * assigned reviewers as stacked avatars, or a dashed "Assign" pill when empty.
 * Uses DropdownMenu (modal) — the same reliable pattern as the Ads status cell —
 * so the list is fully hoverable/clickable inside a clickable table row.
 */
export function InlineAssignee({
    creative,
    reviewers,
    baseUrl,
    canEdit,
}: {
    creative: Creative;
    reviewers: Reviewer[];
    baseUrl: string;
    canEdit: boolean;
}) {
    // Optimistic local copy so toggles feel instant and the menu stays open.
    const [selectedIds, setSelectedIds] = useState<number[]>(
        creative.assigned_reviewers.map((r) => r.id),
    );

    useEffect(() => {
        setSelectedIds(creative.assigned_reviewers.map((r) => r.id));
    }, [creative.assigned_reviewers]);

    const toggle = (id: number) => {
        const next = selectedIds.includes(id)
            ? selectedIds.filter((x) => x !== id)
            : [...selectedIds, id];
        setSelectedIds(next);
        router.put(
            `${baseUrl}/${creative.id}`,
            { assigned_reviewer_ids: next },
            { preserveScroll: true, preserveState: true },
        );
    };

    const selected = reviewers.filter((r) => selectedIds.includes(r.id));

    const avatars = (
        <div className="flex -space-x-1.5">
            {selected.map((r) => (
                <Tooltip key={r.id}>
                    <TooltipTrigger asChild>
                        <span className="rounded-full ring-2 ring-white dark:ring-zinc-900">
                            <InitialAvatar
                                name={r.name}
                                className="h-7 w-7 bg-blue-500/[0.12] text-blue-600 dark:text-blue-400"
                            />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{r.name}</TooltipContent>
                </Tooltip>
            ))}
        </div>
    );

    // Read-only when the user can't edit creatives.
    if (!canEdit) {
        return selected.length > 0 ? (
            avatars
        ) : (
            <span className="font-mono text-[12px] text-gray-300 dark:text-gray-700">
                —
            </span>
        );
    }

    return (
        <div onClick={(e) => e.stopPropagation()}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {selected.length > 0 ? (
                        <button
                            type="button"
                            className="cursor-pointer rounded-full transition-opacity hover:opacity-80"
                        >
                            {avatars}
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-full border border-dashed border-black/15 px-2 py-1 font-mono text-[11px] text-gray-400 transition-colors hover:border-emerald-500/40 hover:text-emerald-600 dark:border-white/15 dark:text-gray-500 dark:hover:text-emerald-400"
                        >
                            <UserPlus className="h-3 w-3" /> Assign
                        </button>
                    )}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-56">
                    <DropdownMenuLabel className="font-mono text-[10px] font-medium tracking-widest text-gray-400 uppercase dark:text-gray-600">
                        Assign reviewers
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {reviewers.length === 0 ? (
                        <div className="px-2 py-3 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                            No reviewers available
                        </div>
                    ) : (
                        reviewers.map((r) => {
                            const isSelected = selectedIds.includes(r.id);
                            return (
                                <DropdownMenuItem
                                    key={r.id}
                                    onSelect={(e) => {
                                        e.preventDefault();
                                        toggle(r.id);
                                    }}
                                    className="flex items-center gap-2 font-mono text-[12px]"
                                >
                                    <InitialAvatar
                                        name={r.name}
                                        className="h-5 w-5 bg-blue-500/[0.12] text-[9px] text-blue-600 dark:text-blue-400"
                                    />
                                    <span className="flex-1 truncate">
                                        {r.name}
                                    </span>
                                    {isSelected && (
                                        <Check className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    )}
                                </DropdownMenuItem>
                            );
                        })
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

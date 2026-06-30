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
 *
 * Selections are staged locally — toggling a reviewer only updates the pending
 * set, and nothing is saved until the user clicks "Assign". This keeps the menu
 * open while picking several people instead of firing (and collapsing) on every
 * click.
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
    // Saved reviewers (what the trigger avatars reflect).
    const savedIds = creative.assigned_reviewers.map((r) => r.id);

    const [open, setOpen] = useState(false);
    // Pending edits — only committed on "Assign".
    const [pendingIds, setPendingIds] = useState<number[]>(savedIds);
    const [saving, setSaving] = useState(false);

    // Reset the pending set to what's saved whenever the menu opens or the
    // underlying creative changes, so an abandoned edit doesn't linger.
    useEffect(() => {
        if (open) setPendingIds(creative.assigned_reviewers.map((r) => r.id));
    }, [open, creative.assigned_reviewers]);

    const toggle = (id: number) => {
        setPendingIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const dirty =
        pendingIds.length !== savedIds.length ||
        pendingIds.some((id) => !savedIds.includes(id));

    const save = () => {
        router.put(
            `${baseUrl}/${creative.id}`,
            { assigned_reviewer_ids: pendingIds },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setOpen(false),
            },
        );
    };

    const saved = reviewers.filter((r) => savedIds.includes(r.id));

    const avatars = (
        <div className="flex -space-x-1.5">
            {saved.map((r) => (
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
        return saved.length > 0 ? (
            avatars
        ) : (
            <span className="font-mono text-[12px] text-gray-300 dark:text-gray-700">
                —
            </span>
        );
    }

    return (
        <div onClick={(e) => e.stopPropagation()}>
            <DropdownMenu open={open} onOpenChange={setOpen}>
                <DropdownMenuTrigger asChild>
                    {saved.length > 0 ? (
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
                            const isSelected = pendingIds.includes(r.id);
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
                    {reviewers.length > 0 && (
                        <>
                            <DropdownMenuSeparator />
                            <div className="flex items-center gap-2 px-1.5 py-1">
                                <button
                                    type="button"
                                    onClick={save}
                                    disabled={saving || !dirty}
                                    className="flex h-7 flex-1 items-center justify-center rounded-md bg-emerald-600 font-mono text-[11px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {saving ? 'Assigning…' : 'Assign'}
                                </button>
                                {dirty && (
                                    <button
                                        type="button"
                                        onClick={() => setPendingIds(savedIds)}
                                        disabled={saving}
                                        className="flex h-7 items-center rounded-md px-2 font-mono text-[11px] font-medium text-gray-500 transition-colors hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Reset
                                    </button>
                                )}
                            </div>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

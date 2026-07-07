import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { Check, Search, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Creative, Reviewer } from '../types';

/** Two-letter initials (first + last word), ClickUp-style. */
function initials(name: string) {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (first + last).toUpperCase() || '?';
}

// Stable per-person avatar color, keyed by user id. Soft tinted fills + colored
// text to match Artemis' muted palette (not solid saturated swatches).
const AVATAR_COLORS = [
    'bg-blue-500/[0.12] text-blue-600 dark:text-blue-400',
    'bg-emerald-500/[0.12] text-emerald-600 dark:text-emerald-400',
    'bg-amber-500/[0.12] text-amber-600 dark:text-amber-400',
    'bg-purple-500/[0.12] text-purple-600 dark:text-purple-400',
    'bg-rose-500/[0.12] text-rose-600 dark:text-rose-400',
    'bg-indigo-500/[0.12] text-indigo-600 dark:text-indigo-400',
    'bg-teal-500/[0.12] text-teal-600 dark:text-teal-400',
    'bg-cyan-500/[0.12] text-cyan-600 dark:text-cyan-400',
];
const colorFor = (id: number) => AVATAR_COLORS[id % AVATAR_COLORS.length];

function Avatar({
    reviewer,
    className = 'h-7 w-7 text-[11px]',
}: {
    reviewer: Reviewer;
    className?: string;
}) {
    return (
        <span
            className={`flex shrink-0 items-center justify-center rounded-full font-mono font-bold ${colorFor(reviewer.id)} ${className}`}
        >
            {initials(reviewer.name)}
        </span>
    );
}

/**
 * Inline, ClickUp-style reviewer assignment for the creatives table. Shows the
 * assigned reviewers as stacked avatars, or a dashed "Assign" pill when empty.
 *
 * Opens a searchable people picker. Selections are staged locally — toggling a
 * reviewer only updates the pending set, and nothing is saved until the user
 * clicks "Assign". This keeps the picker open while choosing several people
 * instead of firing (and collapsing) on every click.
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
    const [search, setSearch] = useState('');
    // Pending edits — only committed on "Assign".
    const [pendingIds, setPendingIds] = useState<number[]>(savedIds);
    const [saving, setSaving] = useState(false);

    // Reset pending set + search to a clean state whenever the picker opens or
    // the underlying creative changes, so an abandoned edit doesn't linger.
    useEffect(() => {
        if (open) {
            setPendingIds(creative.assigned_reviewers.map((r) => r.id));
            setSearch('');
        }
    }, [open, creative.assigned_reviewers]);

    const toggle = (id: number) => {
        setPendingIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return reviewers;
        return reviewers.filter((r) => r.name.toLowerCase().includes(q));
    }, [reviewers, search]);

    const dirty =
        pendingIds.length !== savedIds.length ||
        pendingIds.some((id) => !savedIds.includes(id));

    const commit = (ids: number[]) => {
        router.put(
            `${baseUrl}/${creative.id}`,
            { assigned_reviewer_ids: ids },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setOpen(false),
            },
        );
    };

    const save = () => commit(pendingIds);
    const unassignAll = () => commit([]);

    const saved = reviewers.filter((r) => savedIds.includes(r.id));

    const avatars = (
        <div className="flex -space-x-1.5">
            {saved.map((r) => (
                <Tooltip key={r.id}>
                    <TooltipTrigger asChild>
                        <span className="rounded-full ring-2 ring-white dark:ring-zinc-900">
                            <Avatar reviewer={r} />
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
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
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
                </PopoverTrigger>
                <PopoverContent align="start" className="w-72 p-0">
                    {/* Search */}
                    <div className="flex items-center gap-2 border-b border-black/8 px-3 dark:border-white/8">
                        <Search className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        <input
                            autoFocus
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search people..."
                            className="h-10 w-full bg-transparent font-mono text-[12px] text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                        />
                    </div>

                    {/* People */}
                    <div className="max-h-[280px] overflow-y-auto p-1.5">
                        <p className="px-2 py-1.5 font-mono text-[10px] font-medium tracking-widest text-gray-400 uppercase dark:text-gray-600">
                            People
                        </p>
                        {filtered.length === 0 ? (
                            <p className="px-2 py-4 text-center font-mono text-[12px] text-gray-400 dark:text-gray-600">
                                No people found.
                            </p>
                        ) : (
                            filtered.map((r) => {
                                const isSelected = pendingIds.includes(r.id);
                                return (
                                    <button
                                        key={r.id}
                                        type="button"
                                        onClick={() => toggle(r.id)}
                                        className="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-stone-100 dark:hover:bg-white/5"
                                    >
                                        <Avatar
                                            reviewer={r}
                                            className="h-7 w-7 text-[10px]"
                                        />
                                        <span className="flex-1 truncate font-mono text-[13px] text-gray-800 dark:text-gray-100">
                                            {r.name}
                                        </span>
                                        {isSelected && (
                                            <Check className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>

                    {/* Footer actions */}
                    <div className="flex items-center gap-1.5 border-t border-black/8 p-1.5 dark:border-white/8">
                        {(savedIds.length > 0 || pendingIds.length > 0) && (
                            <button
                                type="button"
                                onClick={unassignAll}
                                disabled={saving}
                                className="flex h-9 items-center justify-center rounded-lg border border-black/8 px-3 font-mono text-[12px] font-medium text-rose-600 transition-colors hover:bg-rose-50 disabled:opacity-50 dark:border-white/8 dark:text-rose-400 dark:hover:bg-rose-500/10"
                            >
                                Unassign
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={save}
                            disabled={saving || !dirty}
                            className="flex h-9 flex-1 items-center justify-center rounded-lg bg-emerald-600 font-mono text-[12px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {saving
                                ? 'Saving…'
                                : dirty
                                  ? `Assign ${pendingIds.length} reviewer${pendingIds.length === 1 ? '' : 's'}`
                                  : 'No changes'}
                        </button>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}

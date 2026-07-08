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
import { Check, Search, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface OwnerOption {
    id: number;
    name: string;
}

/** Two-letter initials (first + last word), ClickUp-style. */
function initials(name: string) {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (first + last).toUpperCase() || '?';
}

// Stable per-person avatar color, keyed by user id. Soft tinted fills to match
// Artemis' muted palette (mirrors the creatives assignee avatars).
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
    owner,
    className = 'h-7 w-7 text-[11px]',
}: {
    owner: OwnerOption;
    className?: string;
}) {
    return (
        <span
            className={`flex shrink-0 items-center justify-center rounded-full font-mono font-bold ${colorFor(owner.id)} ${className}`}
        >
            {initials(owner.name)}
        </span>
    );
}

/**
 * Inline, ClickUp-style single owner assignment for the ad-accounts table.
 * Shows the current owner as an avatar, or a dashed "Assign" pill when empty.
 *
 * Single-select: picking a person commits immediately (and closes the popover),
 * unlike the multi-select creatives assignee which stages selections.
 */
export function InlineOwner({
    owner,
    users,
    canEdit,
    saving = false,
    onAssign,
}: {
    owner: OwnerOption | null;
    users: OwnerOption[];
    canEdit: boolean;
    saving?: boolean;
    onAssign: (ownerId: number | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return users;
        return users.filter((u) => u.name.toLowerCase().includes(q));
    }, [users, search]);

    const select = (id: number | null) => {
        onAssign(id);
        setOpen(false);
    };

    const trigger = owner ? (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="rounded-full ring-2 ring-white dark:ring-zinc-900">
                    <Avatar owner={owner} />
                </span>
            </TooltipTrigger>
            <TooltipContent>{owner.name}</TooltipContent>
        </Tooltip>
    ) : null;

    // Read-only when the user can't manage accounts.
    if (!canEdit) {
        return owner ? (
            trigger
        ) : (
            <span className="font-mono text-[12px] text-gray-300 dark:text-gray-700">
                —
            </span>
        );
    }

    return (
        <div onClick={(e) => e.stopPropagation()}>
            <Popover
                open={open}
                onOpenChange={(o) => {
                    setOpen(o);
                    if (o) setSearch('');
                }}
            >
                <PopoverTrigger asChild>
                    {owner ? (
                        <button
                            type="button"
                            className="cursor-pointer rounded-full transition-opacity hover:opacity-80 disabled:opacity-50"
                            disabled={saving}
                        >
                            {trigger}
                        </button>
                    ) : (
                        <button
                            type="button"
                            disabled={saving}
                            title="Assign owner"
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-dashed border-black/20 text-gray-400 transition-colors hover:border-emerald-500/50 hover:text-emerald-600 disabled:opacity-50 dark:border-white/20 dark:text-gray-500 dark:hover:text-emerald-400"
                        >
                            <UserPlus className="h-3.5 w-3.5" />
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
                            Owner
                        </p>
                        {filtered.length === 0 ? (
                            <p className="px-2 py-4 text-center font-mono text-[12px] text-gray-400 dark:text-gray-600">
                                No people found.
                            </p>
                        ) : (
                            filtered.map((u) => {
                                const isSelected = owner?.id === u.id;
                                return (
                                    <button
                                        key={u.id}
                                        type="button"
                                        onClick={() => select(u.id)}
                                        className="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-stone-100 dark:hover:bg-white/5"
                                    >
                                        <Avatar
                                            owner={u}
                                            className="h-7 w-7 text-[10px]"
                                        />
                                        <span className="flex-1 truncate font-mono text-[13px] text-gray-800 dark:text-gray-100">
                                            {u.name}
                                        </span>
                                        {isSelected && (
                                            <Check className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>

                    {/* Footer — unassign */}
                    {owner && (
                        <div className="border-t border-black/8 p-1.5 dark:border-white/8">
                            <button
                                type="button"
                                onClick={() => select(null)}
                                disabled={saving}
                                className="flex h-9 w-full items-center justify-center rounded-lg border border-black/8 px-3 font-mono text-[12px] font-medium text-rose-600 transition-colors hover:bg-rose-50 disabled:opacity-50 dark:border-white/8 dark:text-rose-400 dark:hover:bg-rose-500/10"
                            >
                                Unassign
                            </button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </div>
    );
}

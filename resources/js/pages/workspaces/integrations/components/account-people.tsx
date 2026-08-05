import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import clsx from 'clsx';
import { Info, Search, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface AdAccountPerson {
    id: number;
    meta_user_id: string;
    name: string | null;
    /** Admin | Advertiser | Draft | Analyst — derived from Meta's task list. */
    role: string | null;
    /** SYSTEM_USER / ADMIN_SYSTEM_USER when the assignee is a system user. */
    user_type: string | null;
    /**
     * 'portfolio' — complete list from Business Manager.
     * 'connected_user' — partial fallback for accounts in no portfolio.
     */
    source: 'portfolio' | 'connected_user';
}

/** Two-letter initials (first + last word), matching the owner avatars. */
function initials(name: string) {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (first + last).toUpperCase() || '?';
}

// Stable per-person avatar colour keyed by the Meta user id, so the same
// person keeps the same tint across every account row.
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

/** Hash the id string — Meta user ids overflow Number, so don't parse them. */
function colorFor(metaUserId: string) {
    let hash = 0;
    for (let i = 0; i < metaUserId.length; i++) {
        hash = (hash * 31 + metaUserId.charCodeAt(i)) % 100000;
    }
    return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

const ROLE_STYLES: Record<string, string> = {
    Admin: 'bg-rose-500/[0.12] text-rose-600 dark:text-rose-400',
    Advertiser: 'bg-blue-500/[0.12] text-blue-600 dark:text-blue-400',
    Draft: 'bg-amber-500/[0.12] text-amber-600 dark:text-amber-400',
    Analyst: 'bg-stone-500/[0.12] text-stone-600 dark:text-stone-400',
};

function RoleBadge({ role }: { role: string | null }) {
    if (!role) return null;
    return (
        <span
            className={clsx(
                'shrink-0 rounded-md px-1.5 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                ROLE_STYLES[role] ??
                    'bg-stone-500/[0.12] text-stone-600 dark:text-stone-400',
            )}
        >
            {role}
        </span>
    );
}

function PersonAvatar({
    person,
    className = 'h-7 w-7 text-[10px]',
}: {
    person: AdAccountPerson;
    className?: string;
}) {
    return (
        <span
            className={clsx(
                'flex shrink-0 items-center justify-center rounded-full font-mono font-bold',
                colorFor(person.meta_user_id),
                className,
            )}
        >
            {initials(person.name ?? '?')}
        </span>
    );
}

const MAX_VISIBLE = 3;

/**
 * Overlapping avatar stack for the people Meta reports as having access to an
 * ad account. Clicking opens a searchable popover listing everyone with the
 * role Business Manager assigns them.
 */
export function AccountPeople({
    people,
    accountName,
    hasBusiness,
}: {
    people: AdAccountPerson[];
    accountName: string;
    /** False when the account is in no business portfolio — see the empty state. */
    hasBusiness: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return people;
        return people.filter(
            (p) =>
                (p.name ?? '').toLowerCase().includes(q) ||
                (p.role ?? '').toLowerCase().includes(q),
        );
    }, [people, search]);

    // Meta enumerates an account's people only through a business portfolio.
    // Without one we fall back to the connected Facebook users, so an empty
    // list here means nobody has connected — not that nobody has access.
    if (people.length === 0 && !hasBusiness) {
        return (
            <span
                title="This ad account isn't in a business portfolio, so Meta won't enumerate its people. Nobody who has connected their Facebook to Artemis can reach it either."
                className="font-mono text-[11px] text-gray-300 italic dark:text-gray-700"
            >
                No portfolio
            </span>
        );
    }

    if (people.length === 0) {
        return (
            <span
                title="No people synced yet — run metaads:sync-ad-account-people."
                className="font-mono text-[12px] text-gray-300 dark:text-gray-700"
            >
                —
            </span>
        );
    }

    const visible = people.slice(0, MAX_VISIBLE);
    const overflow = people.length - visible.length;
    // Reverse-looked-up from connected accounts, so it can't see everyone.
    const isPartial = people.every((p) => p.source === 'connected_user');

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
                    <button
                        type="button"
                        title={`${people.length} ${people.length === 1 ? 'person has' : 'people have'} access`}
                        className="flex cursor-pointer items-center -space-x-1.5 rounded-full transition-opacity hover:opacity-80"
                    >
                        {visible.map((p) => (
                            <span
                                key={p.id}
                                className="rounded-full ring-2 ring-white dark:ring-zinc-900"
                            >
                                <PersonAvatar person={p} />
                            </span>
                        ))}
                        {overflow > 0 && (
                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-stone-100 font-mono text-[10px] font-bold text-gray-500 ring-2 ring-white dark:bg-zinc-800 dark:text-gray-400 dark:ring-zinc-900">
                                +{overflow}
                            </span>
                        )}
                    </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-80 p-0">
                    <div className="flex items-center gap-2 border-b border-black/8 px-3 py-2.5 dark:border-white/8">
                        <Users className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                                {accountName}
                            </p>
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                {people.length}{' '}
                                {people.length === 1 ? 'person' : 'people'} with
                                access
                            </p>
                        </div>
                    </div>

                    {isPartial && (
                        <div className="flex items-start gap-2 border-b border-black/8 bg-amber-50 px-3 py-2 dark:border-white/8 dark:bg-amber-500/10">
                            <Info className="mt-0.5 h-3 w-3 shrink-0 text-amber-600 dark:text-amber-400" />
                            <p className="font-mono text-[10px] leading-relaxed text-amber-700 dark:text-amber-400">
                                Partial list. This account isn't in a business
                                portfolio, so Meta won't enumerate its people —
                                these are the users who connected their Facebook
                                to Artemis. Others may have access.
                            </p>
                        </div>
                    )}

                    {people.length > 6 && (
                        <div className="flex items-center gap-2 border-b border-black/8 px-3 dark:border-white/8">
                            <Search className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                            <input
                                autoFocus
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search people..."
                                className="h-9 w-full bg-transparent font-mono text-[12px] text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                            />
                        </div>
                    )}

                    <div className="max-h-[280px] overflow-y-auto p-1.5">
                        {filtered.length === 0 ? (
                            <p className="px-2 py-4 text-center font-mono text-[12px] text-gray-400 dark:text-gray-600">
                                No people found.
                            </p>
                        ) : (
                            filtered.map((p) => (
                                <div
                                    key={p.id}
                                    className="flex items-center gap-2.5 rounded-lg px-2 py-1.5"
                                >
                                    <PersonAvatar person={p} />
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={clsx(
                                                'truncate font-mono text-[13px]',
                                                p.name
                                                    ? 'text-gray-800 dark:text-gray-100'
                                                    : 'text-gray-400 italic dark:text-gray-600',
                                            )}
                                        >
                                            {/* Only reachable with people.skip_unnamed disabled. */}
                                            {p.name ??
                                                'Restricted or unavailable'}
                                        </p>
                                        {p.user_type
                                            ?.toUpperCase()
                                            .includes('SYSTEM_USER') && (
                                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                                System user
                                            </p>
                                        )}
                                    </div>
                                    <RoleBadge role={p.role} />
                                </div>
                            ))
                        )}
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}

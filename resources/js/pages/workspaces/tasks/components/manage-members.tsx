import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { Search, Trash2, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useDebouncedValue } from '../hooks/use-debounced-value';
import type { AssignableSpaceRole, SpaceMember, UserSummary } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    spaceName: string;
    members: SpaceMember[];
    currentUserId: number;
    canManage: boolean;
    loading: boolean;
    onAdd: (userId: number, role: AssignableSpaceRole) => Promise<void>;
    onSearch: (term: string) => Promise<UserSummary[]>;
    onUpdateRole: (
        member: SpaceMember,
        role: AssignableSpaceRole,
    ) => Promise<void>;
    onRemove: (member: SpaceMember) => Promise<void>;
    onError: (cause: unknown) => void;
};

const ROLES: { value: AssignableSpaceRole; label: string; hint: string }[] = [
    { value: 'admin', label: 'Admin', hint: 'Members and structure' },
    { value: 'member', label: 'Member', hint: 'Create and edit tasks' },
    { value: 'viewer', label: 'Viewer', hint: 'Read only' },
];

function MemberRow({
    member,
    isYou,
    canManage,
    onUpdateRole,
    onRemove,
    onError,
}: {
    member: SpaceMember;
    isYou: boolean;
    canManage: boolean;
    onUpdateRole: (
        member: SpaceMember,
        role: AssignableSpaceRole,
    ) => Promise<void>;
    onRemove: (member: SpaceMember) => Promise<void>;
    onError: (cause: unknown) => void;
}) {
    const getInitials = useInitials();
    const [busy, setBusy] = useState(false);
    const isOwner = member.role === 'owner';

    async function run(action: () => Promise<void>) {
        setBusy(true);

        try {
            await action();
        } catch (cause) {
            onError(cause);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div
            className={cn(
                'flex items-center gap-2.5 border-b border-black/6 py-2.5 last:border-b-0 dark:border-white/6',
                busy && 'opacity-60',
            )}
        >
            <span
                aria-hidden="true"
                className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground"
            >
                {getInitials(member.name)}
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium tracking-tight">
                    {member.name}
                    {isYou && (
                        <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                            you
                        </span>
                    )}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {member.email}
                </p>
            </div>

            {isOwner ? (
                <span className="inline-flex shrink-0 items-center rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset">
                    Owner
                </span>
            ) : (
                <Select
                    value={member.role}
                    disabled={busy || !canManage}
                    onValueChange={(value) =>
                        void run(() =>
                            onUpdateRole(member, value as AssignableSpaceRole),
                        )
                    }
                >
                    <SelectTrigger
                        aria-label={`Role of ${member.name}`}
                        className="h-8 w-[8.5rem] shrink-0 rounded-lg border-black/8 bg-white text-xs dark:border-white/8 dark:bg-zinc-900"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ROLES.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                                <span className="ml-1 text-xs text-muted-foreground">
                                    {role.hint}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            <button
                type="button"
                disabled={busy || isOwner || !canManage}
                onClick={() => void run(() => onRemove(member))}
                aria-label={`Remove ${member.name}`}
                className="flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-200 ease-out hover:bg-destructive/10 hover:text-destructive disabled:pointer-events-none disabled:opacity-30"
            >
                <Trash2 className="size-3.5" />
            </button>
        </div>
    );
}

/** The shortest term the candidate endpoint will search on. */
const MIN_SEARCH = 2;

function CandidateRow({
    candidate,
    disabled,
    adding,
    onAdd,
}: {
    candidate: UserSummary;
    disabled: boolean;
    adding: boolean;
    onAdd: () => void;
}) {
    const getInitials = useInitials();

    return (
        <li className="flex items-center gap-2.5 border-b border-black/6 py-2 last:border-b-0 dark:border-white/6">
            <span
                aria-hidden="true"
                className="flex size-7 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-medium text-muted-foreground ring-1 ring-border ring-inset dark:bg-zinc-900"
            >
                {getInitials(candidate.name)}
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium tracking-tight">
                    {candidate.name}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {candidate.email}
                </p>
            </div>

            <Button
                type="button"
                size="sm"
                disabled={disabled}
                onClick={onAdd}
                className="h-8 shrink-0 px-3 font-semibold"
            >
                <UserPlus className="size-3.5" />
                {adding ? 'Adding…' : 'Add'}
            </Button>
        </li>
    );
}

/** Per-space member editor: add an account, change a role, remove access. */
export function ManageMembers({
    open,
    onOpenChange,
    spaceName,
    members,
    currentUserId,
    canManage,
    loading,
    onAdd,
    onSearch,
    onUpdateRole,
    onRemove,
    onError,
}: Props) {
    const [term, setTerm] = useState('');
    const [role, setRole] = useState<AssignableSpaceRole>('member');
    const [candidates, setCandidates] = useState<UserSummary[]>([]);
    const [searching, setSearching] = useState(false);
    const [addingId, setAddingId] = useState<number | null>(null);

    const debouncedTerm = useDebouncedValue(term);
    const query = debouncedTerm.trim();
    const searchable = query.length >= MIN_SEARCH;

    useEffect(() => {
        if (!open || !canManage || !searchable) {
            setCandidates([]);
            setSearching(false);

            return;
        }

        let active = true;
        setSearching(true);

        onSearch(query)
            .then((found) => {
                if (active) {
                    setCandidates(found);
                }
            })
            .catch((cause: unknown) => {
                if (active) {
                    setCandidates([]);
                    onError(cause);
                }
            })
            .finally(() => {
                if (active) {
                    setSearching(false);
                }
            });

        // A slower earlier search must not overwrite a later one.
        return () => {
            active = false;
        };
    }, [open, canManage, searchable, query, onSearch, onError]);

    /** Drop the search when the dialog closes so it reopens clean. */
    useEffect(() => {
        if (!open) {
            setTerm('');
            setRole('member');
        }
    }, [open]);

    async function add(candidate: UserSummary) {
        if (addingId !== null) {
            return;
        }

        setAddingId(candidate.id);

        try {
            await onAdd(candidate.id, role);

            // They are a member now, so they are no longer a candidate — but
            // keep the term, since people are usually added in batches.
            setCandidates((current) =>
                current.filter((item) => item.id !== candidate.id),
            );
        } catch (cause) {
            // A 422 here means the picker went stale — someone else added them,
            // or the account was deleted. Laravel puts that first field error in
            // `message`, which is what the board's reporter already surfaces.
            onError(cause);
        } finally {
            setAddingId(null);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="text-xl font-semibold tracking-[-0.022em]">
                        Members
                    </DialogTitle>
                    <DialogDescription>
                        Who can reach {spaceName}, and what they may do in it.
                        Members can be assigned tasks; admins can also change
                        the lists, statuses and this list of people.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col">
                    {loading
                        ? ['w-32', 'w-24', 'w-28'].map((width) => (
                              <div
                                  key={width}
                                  className="flex items-center gap-2.5 border-b border-black/6 py-3 last:border-b-0 dark:border-white/6"
                              >
                                  <Skeleton className="size-8 shrink-0 rounded-full" />
                                  <Skeleton className={cn('h-4', width)} />
                              </div>
                          ))
                        : members.map((member) => (
                              <MemberRow
                                  key={member.id}
                                  member={member}
                                  isYou={member.id === currentUserId}
                                  canManage={canManage}
                                  onUpdateRole={onUpdateRole}
                                  onRemove={onRemove}
                                  onError={onError}
                              />
                          ))}
                </div>

                {canManage && (
                    <div className="mt-1 rounded-xl border border-border bg-muted/40 p-2.5">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    type="search"
                                    value={term}
                                    onChange={(event) =>
                                        setTerm(event.target.value)
                                    }
                                    placeholder="Search people by name or email"
                                    aria-label="Search people to add"
                                    aria-controls="member-candidates"
                                    className="h-9 w-full min-w-0 rounded-lg border border-black/8 bg-white pr-2.5 pl-8 text-sm transition-all duration-200 ease-out outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                                />
                            </div>

                            <Select
                                value={role}
                                onValueChange={(value) =>
                                    setRole(value as AssignableSpaceRole)
                                }
                            >
                                <SelectTrigger
                                    aria-label="Role to add people with"
                                    className="h-9 w-[8.5rem] rounded-lg border-black/8 bg-white text-xs dark:border-white/8 dark:bg-zinc-900"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ROLES.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                {item.hint}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div id="member-candidates" aria-live="polite">
                            {!searchable ? (
                                <p className="px-0.5 pt-2.5 text-xs text-muted-foreground">
                                    Type at least {MIN_SEARCH} characters to
                                    find someone. They join as{' '}
                                    {ROLES.find(
                                        (item) => item.value === role,
                                    )?.label.toLowerCase()}
                                    .
                                </p>
                            ) : searching ? (
                                <ul className="mt-1.5">
                                    {['w-28', 'w-36'].map((width) => (
                                        <li
                                            key={width}
                                            className="flex items-center gap-2.5 border-b border-black/6 py-2.5 last:border-b-0 dark:border-white/6"
                                        >
                                            <Skeleton className="size-7 shrink-0 rounded-full" />
                                            <Skeleton
                                                className={cn('h-3.5', width)}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : candidates.length === 0 ? (
                                <p className="px-0.5 pt-2.5 text-xs text-muted-foreground">
                                    Nobody outside this space matches “{query}”.
                                </p>
                            ) : (
                                <ul className="mt-1.5">
                                    {candidates.map((candidate) => (
                                        <CandidateRow
                                            key={candidate.id}
                                            candidate={candidate}
                                            disabled={addingId !== null}
                                            adding={addingId === candidate.id}
                                            onAdd={() => void add(candidate)}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { statusColor, tint } from '../lib/task-format';
import type { TaskStatus, TaskStatusInput, TaskStatusType } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    spaceName: string;
    statuses: TaskStatus[];
    loading: boolean;
    onCreate: (input: TaskStatusInput) => Promise<void>;
    onUpdate: (
        status: TaskStatus,
        input: Partial<TaskStatusInput>,
    ) => Promise<void>;
    onDelete: (status: TaskStatus) => Promise<void>;
    onError: (cause: unknown) => void;
};

const TYPES: { value: TaskStatusType; label: string; hint: string }[] = [
    { value: 'not_started', label: 'Not started', hint: 'Work not begun' },
    { value: 'active', label: 'Active', hint: 'Work in progress' },
    { value: 'done', label: 'Done', hint: 'Counts as complete' },
    { value: 'closed', label: 'Closed', hint: 'Counts as complete' },
];

/**
 * Status swatches, validated with the dataviz palette checker in both themes:
 * all pass the lightness band, chroma floor and normal-vision separation in
 * light mode. Two knowingly-accepted exceptions, both relieved by the direct
 * labels beside every swatch and the 2px gaps between bar segments:
 * green/rose sit at CVD ΔE 7.0 (deutan), and the gold cannot satisfy both
 * lightness bands at once — a single stored hex serves both themes, and for a
 * yellow the light-mode and dark-mode bands do not overlap. It still clears
 * 3:1 contrast on the dark surface.
 */
const PALETTE = [
    '#7B68EE',
    '#CE9C3E',
    '#0F8C77',
    '#CC3B2E',
    '#2D7CC9',
    '#C85A76',
    '#5C9130',
    '#8E90A6',
];

function ColorPicker({
    value,
    onPick,
}: {
    value: string | null;
    onPick: (color: string) => void;
}) {
    const current = statusColor(value);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label="Change colour"
                    className="size-5 shrink-0 rounded-full transition-transform duration-200 ease-out hover:scale-110"
                    style={{
                        backgroundColor: current,
                        boxShadow: `0 0 0 3px ${tint(current, 0.18)}`,
                    }}
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-auto p-2">
                <div className="grid grid-cols-4 gap-1.5">
                    {PALETTE.map((color) => (
                        <button
                            key={color}
                            type="button"
                            aria-label={color}
                            onClick={() => onPick(color)}
                            className={cn(
                                'size-6 rounded-full transition-transform duration-200 ease-out hover:scale-110',
                                color.toLowerCase() === current.toLowerCase() &&
                                    'ring-2 ring-foreground ring-offset-2 ring-offset-(--color-popover)',
                            )}
                            style={{ backgroundColor: color }}
                        />
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function StatusRow({
    status,
    index,
    total,
    onUpdate,
    onMove,
    onDelete,
    onError,
}: {
    status: TaskStatus;
    index: number;
    total: number;
    onUpdate: (
        status: TaskStatus,
        input: Partial<TaskStatusInput>,
    ) => Promise<void>;
    onMove: (index: number, direction: -1 | 1) => Promise<void>;
    onDelete: (status: TaskStatus) => Promise<void>;
    onError: (cause: unknown) => void;
}) {
    const [name, setName] = useState(status.name);
    const [busy, setBusy] = useState(false);

    async function run(action: () => Promise<void>) {
        setBusy(true);

        try {
            await action();
        } catch (cause) {
            onError(cause);
            setName(status.name);
        } finally {
            setBusy(false);
        }
    }

    function commitName() {
        const trimmed = name.trim();

        if (trimmed === '' || trimmed === status.name) {
            setName(status.name);

            return;
        }

        void run(() => onUpdate(status, { name: trimmed }));
    }

    return (
        <div
            className={cn(
                'flex items-center gap-2.5 border-b border-black/6 py-2.5 last:border-b-0 dark:border-white/6',
                busy && 'opacity-60',
            )}
        >
            <ColorPicker
                value={status.color}
                onPick={(color) => void run(() => onUpdate(status, { color }))}
            />

            <input
                value={name}
                disabled={busy}
                onChange={(event) => setName(event.target.value)}
                onBlur={commitName}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.currentTarget.blur();
                    }

                    if (event.key === 'Escape') {
                        setName(status.name);
                    }
                }}
                aria-label={`Name of ${status.name}`}
                className="h-8 min-w-0 flex-1 rounded-lg border border-black/8 bg-white px-2.5 text-sm transition-all duration-200 ease-out outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
            />

            <Select
                value={status.type}
                onValueChange={(value) =>
                    void run(() =>
                        onUpdate(status, { type: value as TaskStatusType }),
                    )
                }
            >
                <SelectTrigger
                    aria-label={`Type of ${status.name}`}
                    className="h-8 w-[8.5rem] rounded-lg border-black/8 bg-white text-xs dark:border-white/8 dark:bg-zinc-900"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {TYPES.map((type) => (
                        <SelectItem key={type.value} value={type.value}>
                            {type.label}
                            <span className="ml-1 text-xs text-muted-foreground">
                                {type.hint}
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        disabled={busy || status.is_default}
                        onClick={() =>
                            void run(() =>
                                onUpdate(status, { is_default: true }),
                            )
                        }
                        aria-label={`Make ${status.name} the default`}
                        aria-pressed={status.is_default}
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center rounded-md transition-colors duration-200 ease-out',
                            status.is_default
                                ? 'text-amber-500'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        <Star
                            className="size-3.5"
                            fill={status.is_default ? 'currentColor' : 'none'}
                        />
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    {status.is_default
                        ? 'New tasks land here'
                        : 'Make this the default'}
                </TooltipContent>
            </Tooltip>

            <div className="flex shrink-0 flex-col">
                <button
                    type="button"
                    disabled={busy || index === 0}
                    onClick={() => void run(() => onMove(index, -1))}
                    aria-label={`Move ${status.name} up`}
                    className="flex h-3.5 w-5 items-center justify-center rounded text-muted-foreground transition-colors hover:text-foreground disabled:opacity-30"
                >
                    <ChevronUp className="size-3" />
                </button>
                <button
                    type="button"
                    disabled={busy || index === total - 1}
                    onClick={() => void run(() => onMove(index, 1))}
                    aria-label={`Move ${status.name} down`}
                    className="flex h-3.5 w-5 items-center justify-center rounded text-muted-foreground transition-colors hover:text-foreground disabled:opacity-30"
                >
                    <ChevronDown className="size-3" />
                </button>
            </div>

            <button
                type="button"
                disabled={busy}
                onClick={() => void run(() => onDelete(status))}
                aria-label={`Delete ${status.name}`}
                className="flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-200 ease-out hover:bg-destructive/10 hover:text-destructive"
            >
                <Trash2 className="size-3.5" />
            </button>
        </div>
    );
}

/** Per-space status editor: rename, recolour, retype, reorder and remove. */
export function ManageStatuses({
    open,
    onOpenChange,
    spaceName,
    statuses,
    loading,
    onCreate,
    onUpdate,
    onDelete,
    onError,
}: Props) {
    const [name, setName] = useState('');
    const [type, setType] = useState<TaskStatusType>('not_started');
    const [color, setColor] = useState(PALETTE[0]);
    const [saving, setSaving] = useState(false);

    /**
     * Swap a status with its neighbour. Stored positions are sparse (`max + 1`
     * on create) and may even collide, so trade the two values outright rather
     * than trusting the row index, and fall back to index order when the pair
     * already shares a position.
     */
    async function move(index: number, direction: -1 | 1) {
        const current = statuses[index];
        const neighbour = statuses[index + direction];

        if (current === undefined || neighbour === undefined) {
            return;
        }

        const [next, swapped] =
            current.position === neighbour.position
                ? [index + direction, index]
                : [neighbour.position, current.position];

        await onUpdate(current, { position: next });
        await onUpdate(neighbour, { position: swapped });
    }

    async function submit(event: React.FormEvent) {
        event.preventDefault();

        if (name.trim() === '' || saving) {
            return;
        }

        setSaving(true);

        try {
            await onCreate({ name: name.trim(), type, color });
            setName('');
            setType('not_started');
            setColor(PALETTE[0]);
        } catch (cause) {
            onError(cause);
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="text-xl font-semibold tracking-[-0.022em]">
                        Statuses
                    </DialogTitle>
                    <DialogDescription>
                        Every status below belongs to {spaceName} alone. They
                        set the groups on the list, the columns on the board,
                        and which of them counts a task as finished.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col">
                    {loading ? (
                        ['w-32', 'w-24', 'w-28'].map((width) => (
                            <div
                                key={width}
                                className="flex items-center gap-2.5 border-b border-black/6 py-3.5 last:border-b-0 dark:border-white/6"
                            >
                                <Skeleton className="size-5 shrink-0 rounded-full" />
                                <Skeleton className={cn('h-4', width)} />
                            </div>
                        ))
                    ) : statuses.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-black/12 px-4 py-8 text-center text-sm text-muted-foreground dark:border-white/12">
                            This space has no statuses yet. Add the first one
                            below.
                        </p>
                    ) : (
                        statuses.map((status, index) => (
                            <StatusRow
                                key={status.id}
                                status={status}
                                index={index}
                                total={statuses.length}
                                onUpdate={onUpdate}
                                onMove={move}
                                onDelete={onDelete}
                                onError={onError}
                            />
                        ))
                    )}
                </div>

                <form
                    onSubmit={submit}
                    className="mt-1 flex flex-wrap items-center gap-2.5 rounded-xl border border-border bg-muted/40 p-2.5"
                >
                    <ColorPicker value={color} onPick={setColor} />

                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder="New status name"
                        aria-label="New status name"
                        className="h-9 min-w-0 flex-1 rounded-lg border border-black/8 bg-white px-2.5 text-sm transition-all duration-200 ease-out outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                    />

                    <Select
                        value={type}
                        onValueChange={(value) =>
                            setType(value as TaskStatusType)
                        }
                    >
                        <SelectTrigger
                            aria-label="New status type"
                            className="h-9 w-[8.5rem] rounded-lg border-black/8 bg-white text-xs dark:border-white/8 dark:bg-zinc-900"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {TYPES.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        {item.hint}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button
                        type="submit"
                        size="sm"
                        disabled={name.trim() === '' || saving || loading}
                        className="h-9 px-3.5 font-semibold"
                    >
                        <Plus className="size-3.5" />
                        Add status
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

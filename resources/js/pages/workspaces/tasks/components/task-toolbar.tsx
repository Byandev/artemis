import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    ArrowUpDown,
    LayoutGrid,
    List,
    Plus,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import type { BoardView } from '../hooks/use-task-board';
import type { TaskFilters, TaskStatus } from '../types';

type Props = {
    filters: TaskFilters;
    statuses: TaskStatus[];
    view: BoardView;
    onView: (view: BoardView) => void;
    onFilter: <K extends keyof TaskFilters>(
        key: K,
        value: TaskFilters[K],
    ) => void;
    onReset: () => void;
    onAddTask: () => void;
    canAddTask: boolean;
    addTaskBlockedReason: string | null;
};

const PRIORITIES = ['urgent', 'high', 'normal', 'low'] as const;

const SORTS = [
    { value: 'position', label: 'Manual' },
    { value: '-priority', label: 'Priority' },
    { value: 'due_at', label: 'Due date' },
    { value: '-created_at', label: 'Newest' },
] as const;

const VIEWS = [
    { value: 'list', label: 'List', icon: List },
    { value: 'board', label: 'Board', icon: LayoutGrid },
] as const;

/**
 * Radix rejects an empty string as a menu value, so the "no filter" choice
 * travels as this sentinel and is unwrapped before it reaches the board.
 */
const ANY = 'any';

const unwrap = (value: string): string => (value === ANY ? '' : value);
const wrap = (value: string): string => (value === '' ? ANY : value);

const capitalise = (value: string): string =>
    value[0].toUpperCase() + value.slice(1);

export function TaskToolbar({
    filters,
    statuses,
    view,
    onView,
    onFilter,
    onReset,
    onAddTask,
    canAddTask,
    addTaskBlockedReason,
}: Props) {
    // Search has its own field, so it is not counted on the Filter chip.
    const activeFilters = [filters.status_id, filters.priority].filter(
        (value) => value !== '',
    ).length;

    const dirty = activeFilters > 0 || filters.search !== '';

    const sortLabel =
        SORTS.find((sort) => sort.value === filters.sort)?.label ?? 'Manual';

    const addButton = (
        <Button
            type="button"
            size="sm"
            onClick={onAddTask}
            disabled={!canAddTask}
            className="h-9 px-3.5 font-semibold"
        >
            <Plus className="size-3.5" />
            Add task
        </Button>
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div
                role="group"
                aria-label="View"
                className="inline-flex items-center gap-0.5 rounded-lg bg-muted/70 p-0.5 ring-1 ring-black/6 dark:ring-white/6"
            >
                {VIEWS.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        onClick={() => onView(item.value)}
                        aria-pressed={view === item.value}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-all duration-200 ease-out',
                            view === item.value
                                ? 'bg-white text-foreground dark:bg-zinc-900'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <item.icon className="size-3.5" />
                        {item.label}
                    </button>
                ))}
            </div>

            <div className="mx-1 hidden h-5 w-px bg-border sm:block" />

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <input
                    value={filters.search}
                    onChange={(event) => onFilter('search', event.target.value)}
                    placeholder="Search tasks"
                    aria-label="Search tasks"
                    className="h-9 w-56 rounded-lg border border-black/8 bg-white pr-3 pl-9 text-sm transition-all duration-200 ease-out outline-none placeholder:text-muted-foreground/70 hover:border-muted-foreground/40 focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                />
            </div>

            {/*
             * Status and priority share one control, so they stop competing
             * with the primary action for attention.
             */}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-9"
                    >
                        <SlidersHorizontal className="size-3.5" />
                        Filter
                        {activeFilters > 0 && (
                            <span className="ml-0.5 flex size-4 items-center justify-center rounded-full bg-emerald-600 text-[0.625rem] font-semibold text-white">
                                {activeFilters}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-52">
                    <DropdownMenuLabel>Status</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={wrap(filters.status_id)}
                        onValueChange={(value) =>
                            onFilter('status_id', unwrap(value))
                        }
                    >
                        <DropdownMenuRadioItem value={ANY}>
                            All statuses
                        </DropdownMenuRadioItem>
                        {statuses.map((status) => (
                            <DropdownMenuRadioItem
                                key={status.id}
                                value={String(status.id)}
                            >
                                {status.name}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>

                    <DropdownMenuSeparator />

                    <DropdownMenuLabel>Priority</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={wrap(filters.priority)}
                        onValueChange={(value) =>
                            onFilter('priority', unwrap(value))
                        }
                    >
                        <DropdownMenuRadioItem value={ANY}>
                            All priorities
                        </DropdownMenuRadioItem>
                        {PRIORITIES.map((priority) => (
                            <DropdownMenuRadioItem
                                key={priority}
                                value={priority}
                            >
                                {capitalise(priority)}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-9 text-muted-foreground"
                    >
                        <ArrowUpDown className="size-3.5" />
                        {sortLabel}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-44">
                    <DropdownMenuLabel>Sort by</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={filters.sort}
                        onValueChange={(value) => onFilter('sort', value)}
                    >
                        {SORTS.map((sort) => (
                            <DropdownMenuRadioItem
                                key={sort.value}
                                value={sort.value}
                            >
                                {sort.label}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            {dirty && (
                <button
                    type="button"
                    onClick={onReset}
                    className="flex items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-muted-foreground transition-colors duration-200 ease-out hover:text-foreground"
                >
                    <X className="size-3" />
                    Clear
                </button>
            )}

            <div className="ml-auto">
                {addTaskBlockedReason === null ? (
                    addButton
                ) : (
                    <Tooltip>
                        {/*
                         * A disabled button swallows pointer events, so the
                         * trigger has to be the wrapper for the reason to be
                         * reachable by mouse and keyboard alike.
                         */}
                        <TooltipTrigger asChild>
                            <span tabIndex={0} className="inline-block">
                                {addButton}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>{addTaskBlockedReason}</TooltipContent>
                    </Tooltip>
                )}
            </div>
        </div>
    );
}

import { Input } from '@/components/ui/input';
import { Search } from 'lucide-react';
import { STATUS_OPTIONS, statusHeaderStyles, statusIcons } from './task-utils';
import { TaskFilter, TaskStatusCounts } from './types';

interface TaskToolbarProps {
    searchValue: string;
    statusCounts: TaskStatusCounts;
    statusFilter: TaskFilter;
    totalTasks: number;
    onSearchChange: (value: string) => void;
    onStatusFilterChange: (value: TaskFilter) => void;
}

export function TaskToolbar({
    searchValue,
    statusCounts,
    statusFilter,
    totalTasks,
    onSearchChange,
    onStatusFilterChange,
}: TaskToolbarProps) {
    return (
        <div className="mb-3 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div className="relative w-full lg:max-w-sm">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <Input
                    value={searchValue}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Search tasks, people..."
                    className="h-9 rounded-lg border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                />
            </div>

            <div className="flex min-w-0 flex-wrap items-center gap-1.5 rounded-lg bg-stone-100 p-1 dark:bg-zinc-900">
                <button
                    type="button"
                    onClick={() => onStatusFilterChange('all')}
                    className={`h-7 rounded-md px-3 font-mono text-[12px] font-medium transition-all ${
                        statusFilter === 'all'
                            ? 'bg-white text-gray-900 shadow-xs dark:bg-zinc-800 dark:text-gray-100'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                    }`}
                >
                    All {totalTasks}
                </button>
                {STATUS_OPTIONS.map((status) => {
                    const StatusIcon = statusIcons[status.value];

                    return (
                        <button
                            key={status.value}
                            type="button"
                            onClick={() => onStatusFilterChange(status.value)}
                            className={`flex h-7 items-center gap-1.5 rounded-md px-3 font-mono text-[12px] font-medium transition-all ${
                                statusFilter === status.value
                                    ? `${statusHeaderStyles[status.value]} shadow-xs`
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            <StatusIcon className="h-3.5 w-3.5" />
                            {status.label} {statusCounts[status.value]}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

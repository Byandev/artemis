import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { ORDER_STATUSES, getStatusBadgeClass } from '@/types/models/Pancake/OrderForDelivery';
import { ChevronDown, Search, X } from 'lucide-react';
import { ParcelStatusEntry } from './rmo-config';

export interface UniquePage {
    id: string;
    name: string;
}

interface Props {
    searchValue: string;
    onSearchChange: (value: string) => void;

    uniquePages: UniquePage[];
    selectedPageId: string;
    onPageChange: (id: string) => void;

    parcelStatusConfig: Record<string, ParcelStatusEntry>;
    parcelStatusOptions: string[];
    selectedParcelStatus: string;
    onParcelStatusChange: (status: string) => void;

    selectedStatus: string;
    onStatusChange: (status: string) => void;
}

export function RmoFilterBar({
    searchValue,
    onSearchChange,
    uniquePages,
    selectedPageId,
    onPageChange,
    parcelStatusConfig,
    parcelStatusOptions,
    selectedParcelStatus,
    onParcelStatusChange,
    selectedStatus,
    onStatusChange,
}: Props) {
    const hasActiveFilters = !!(selectedStatus || selectedPageId || selectedParcelStatus || searchValue);

    const clearFilters = () => {
        onSearchChange('');
        onPageChange('');
        onParcelStatusChange('');
        onStatusChange('');
    };

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2">
            {/* Search */}
            <div className="relative w-64">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    type="text"
                    className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                    placeholder="Search orders…"
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            {/* Divider */}
            <div className="h-5 w-px bg-black/8 dark:bg-white/8" />

            {/* Filter group */}
            <div className="flex items-center gap-1.5">
                {/* Page filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger className={cn(
                        'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                        selectedPageId
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                    )}>
                        <span className={cn('text-[11px] font-normal', selectedPageId ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                            Page
                        </span>
                        <span className={cn('h-3 w-px', selectedPageId ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                        <span className="max-w-[120px] truncate">
                            {uniquePages.find((p) => p.id === selectedPageId)?.name || 'All'}
                        </span>
                        <ChevronDown className="h-3 w-3 opacity-40" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                        <DropdownMenuItem onClick={() => onPageChange('')}>All Pages</DropdownMenuItem>
                        {uniquePages.map((page) => (
                            <DropdownMenuItem key={page.id} onClick={() => onPageChange(page.id)}>
                                {page.name}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* J&T Status filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger className={cn(
                        'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                        selectedParcelStatus
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                    )}>
                        <span className={cn('text-[11px] font-normal', selectedParcelStatus ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                            J&amp;T
                        </span>
                        <span className={cn('h-3 w-px', selectedParcelStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                        <span>{selectedParcelStatus ? parcelStatusConfig[selectedParcelStatus]?.label : 'All'}</span>
                        <ChevronDown className="h-3 w-3 opacity-40" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                        <DropdownMenuItem onClick={() => onParcelStatusChange('')}>All Status</DropdownMenuItem>
                        {parcelStatusOptions.map((s) => {
                            const cfg = parcelStatusConfig[s];
                            return (
                                <DropdownMenuItem key={s} onClick={() => onParcelStatusChange(s)}>
                                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ${cfg.pill}`}>
                                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                                        {cfg.label}
                                    </span>
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Order Status filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger className={cn(
                        'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                        selectedStatus
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                    )}>
                        <span className={cn('text-[11px] font-normal', selectedStatus ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                            Status
                        </span>
                        <span className={cn('h-3 w-px', selectedStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                        <span>{selectedStatus || 'All'}</span>
                        <ChevronDown className="h-3 w-3 opacity-40" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                        <DropdownMenuItem onClick={() => onStatusChange('')}>All Status</DropdownMenuItem>
                        {ORDER_STATUSES.map((s) => (
                            <DropdownMenuItem key={s} onClick={() => onStatusChange(s)}>
                                <span className={getStatusBadgeClass(s)}>{s}</span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {/* Clear filters */}
            {hasActiveFilters && (
                <button
                    onClick={clearFilters}
                    className="flex h-8 items-center gap-1.5 rounded-lg border border-black/6 dark:border-white/6 px-2.5 text-[12px] font-medium text-gray-400 dark:text-gray-500 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-500 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                >
                    <X className="h-3.5 w-3.5" />
                    Clear
                </button>
            )}
        </div>
    );
}

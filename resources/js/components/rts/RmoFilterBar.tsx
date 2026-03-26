import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { ORDER_STATUSES } from '@/types/models/Pancake/OrderForDelivery';
import { ChevronDown, Search, X } from 'lucide-react';
import { orderStatusConfig, ParcelStatusEntry } from './rmo-config';

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

// Shared trigger classes
const triggerBase = 'flex h-8 items-center gap-2 rounded-lg border px-3 font-mono! text-[12px]! font-medium outline-none transition-all cursor-pointer';
const triggerIdle = 'border-black/6 bg-stone-100 text-gray-700 hover:border-black/12 hover:bg-stone-200/60 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12 dark:hover:bg-zinc-700/60';
const triggerActive = 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400';

// Shared dropdown item classes
const itemBase = 'flex items-center gap-2 rounded-md px-2.5 py-1.5 font-mono! text-[12px]! cursor-pointer text-gray-600 dark:text-gray-400';

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
            <div className="relative w-60">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    type="text"
                    className="h-8 w-full rounded-lg border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono! text-[12px]! text-gray-700 dark:text-gray-300 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                    placeholder="Search orders…"
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            {/* Divider */}
            <div className="h-4 w-px bg-black/8 dark:bg-white/8" />

            {/* Filter group */}
            {/*<div className="flex items-center gap-1">*/}

            {/*    /!* Page filter *!/*/}
            {/*    <DropdownMenu>*/}
            {/*        <DropdownMenuTrigger className={cn(triggerBase, selectedPageId ? triggerActive : triggerIdle)}>*/}
            {/*            <span className={cn('font-normal', selectedPageId ? 'text-emerald-400 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>*/}
            {/*                Page*/}
            {/*            </span>*/}
            {/*            <span className={cn('h-3 w-px', selectedPageId ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-black/8 dark:bg-white/8')} />*/}
            {/*            <span className="max-w-[120px] truncate">*/}
            {/*                {uniquePages.find((p) => p.id === selectedPageId)?.name ?? 'All'}*/}
            {/*            </span>*/}
            {/*            <ChevronDown className="h-3 w-3 opacity-30" />*/}
            {/*        </DropdownMenuTrigger>*/}
            {/*        <DropdownMenuContent align="start" className="w-48 max-h-72 overflow-y-auto p-1">*/}
            {/*            <DropdownMenuItem*/}
            {/*                onClick={() => onPageChange('')}*/}
            {/*                className={cn(itemBase, !selectedPageId && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*            >*/}
            {/*                All Pages*/}
            {/*            </DropdownMenuItem>*/}
            {/*            {uniquePages.map((page) => (*/}
            {/*                <DropdownMenuItem*/}
            {/*                    key={page.id}*/}
            {/*                    onClick={() => onPageChange(page.id)}*/}
            {/*                    className={cn(itemBase, selectedPageId === page.id && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*                >*/}
            {/*                    {page.name}*/}
            {/*                </DropdownMenuItem>*/}
            {/*            ))}*/}
            {/*        </DropdownMenuContent>*/}
            {/*    </DropdownMenu>*/}

            {/*    /!* J&T Status filter *!/*/}
            {/*    <DropdownMenu>*/}
            {/*        <DropdownMenuTrigger className={cn(triggerBase, selectedParcelStatus ? triggerActive : triggerIdle)}>*/}
            {/*            <span className={cn('font-normal', selectedParcelStatus ? 'text-emerald-400 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>*/}
            {/*                J&amp;T*/}
            {/*            </span>*/}
            {/*            <span className={cn('h-3 w-px', selectedParcelStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-black/8 dark:bg-white/8')} />*/}
            {/*            {selectedParcelStatus ? (*/}
            {/*                <span className="flex items-center gap-1.5">*/}
            {/*                    <span className={`h-1.5 w-1.5 rounded-full ${parcelStatusConfig[selectedParcelStatus]?.dot}`} />*/}
            {/*                    {parcelStatusConfig[selectedParcelStatus]?.label}*/}
            {/*                </span>*/}
            {/*            ) : (*/}
            {/*                <span>All</span>*/}
            {/*            )}*/}
            {/*            <ChevronDown className="h-3 w-3 opacity-30" />*/}
            {/*        </DropdownMenuTrigger>*/}
            {/*        <DropdownMenuContent align="start" className="w-44 max-h-72 overflow-y-auto p-1">*/}
            {/*            <DropdownMenuItem*/}
            {/*                onClick={() => onParcelStatusChange('')}*/}
            {/*                className={cn(itemBase, !selectedParcelStatus && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*            >*/}
            {/*                All Status*/}
            {/*            </DropdownMenuItem>*/}
            {/*            {parcelStatusOptions.map((s) => {*/}
            {/*                const cfg = parcelStatusConfig[s];*/}
            {/*                return (*/}
            {/*                    <DropdownMenuItem*/}
            {/*                        key={s}*/}
            {/*                        onClick={() => onParcelStatusChange(s)}*/}
            {/*                        className={cn(itemBase, selectedParcelStatus === s && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*                    >*/}
            {/*                        <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${cfg.dot}`} />*/}
            {/*                        {cfg.label}*/}
            {/*                    </DropdownMenuItem>*/}
            {/*                );*/}
            {/*            })}*/}
            {/*        </DropdownMenuContent>*/}
            {/*    </DropdownMenu>*/}

            {/*    /!* Order Status filter *!/*/}
            {/*    <DropdownMenu>*/}
            {/*        <DropdownMenuTrigger className={cn(triggerBase, selectedStatus ? triggerActive : triggerIdle)}>*/}
            {/*            <span className={cn('font-normal', selectedStatus ? 'text-emerald-400 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>*/}
            {/*                Status*/}
            {/*            </span>*/}
            {/*            <span className={cn('h-3 w-px', selectedStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-black/8 dark:bg-white/8')} />*/}
            {/*            {selectedStatus ? (*/}
            {/*                <span className="flex items-center gap-1.5">*/}
            {/*                    <span className={`h-1.5 w-1.5 rounded-full ${orderStatusConfig[selectedStatus]?.dot ?? 'bg-gray-400'}`} />*/}
            {/*                    {selectedStatus}*/}
            {/*                </span>*/}
            {/*            ) : (*/}
            {/*                <span>All</span>*/}
            {/*            )}*/}
            {/*            <ChevronDown className="h-3 w-3 opacity-30" />*/}
            {/*        </DropdownMenuTrigger>*/}
            {/*        <DropdownMenuContent align="start" className="w-52 max-h-72 overflow-y-auto p-1">*/}
            {/*            <DropdownMenuItem*/}
            {/*                onClick={() => onStatusChange('')}*/}
            {/*                className={cn(itemBase, !selectedStatus && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*            >*/}
            {/*                All Status*/}
            {/*            </DropdownMenuItem>*/}
            {/*            {ORDER_STATUSES.map((s) => {*/}
            {/*                const dot = orderStatusConfig[s]?.dot ?? 'bg-gray-400';*/}
            {/*                return (*/}
            {/*                    <DropdownMenuItem*/}
            {/*                        key={s}*/}
            {/*                        onClick={() => onStatusChange(s)}*/}
            {/*                        className={cn(itemBase, selectedStatus === s && 'font-semibold text-gray-800 dark:text-gray-200')}*/}
            {/*                    >*/}
            {/*                        <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${dot}`} />*/}
            {/*                        {s}*/}
            {/*                    </DropdownMenuItem>*/}
            {/*                );*/}
            {/*            })}*/}
            {/*        </DropdownMenuContent>*/}
            {/*    </DropdownMenu>*/}
            {/*</div>*/}

            {/* Clear filters */}
            {hasActiveFilters && (
                <button
                    onClick={clearFilters}
                    className="flex h-8 items-center gap-1.5 rounded-lg border border-black/6 dark:border-white/6 px-2.5 font-mono! text-[12px]! font-medium text-gray-400 dark:text-gray-500 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-500 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                >
                    <X className="h-3 w-3" />
                    Clear
                </button>
            )}
        </div>
    );
}

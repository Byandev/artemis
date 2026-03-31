import { Dispatch, SetStateAction } from 'react';
import { ChevronDown, Filter, RefreshCw, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import DatePicker from '@/components/ui/date-picker';
import { StatusId, StatusOption } from '@/components/inventory/purchased-orders-types';

interface PurchasedOrdersFilterBarProps {
    startDate: string;
    endDate: string;
    onDateChange: (nextStart: string, nextEnd: string) => void;
    query: string;
    setQuery: Dispatch<SetStateAction<string>>;
    statusFilterLabel: string;
    statusOptions: StatusOption[];
    statusOptionTextClass: (status: StatusId) => string;
    setStatusFilter: Dispatch<SetStateAction<string>>;
    onLoad: () => void;
    loading: boolean;
}

export function PurchasedOrdersFilterBar({
    startDate,
    endDate,
    onDateChange,
    query,
    setQuery,
    statusFilterLabel,
    statusOptions,
    statusOptionTextClass,
    setStatusFilter,
    onLoad,
    loading,
}: PurchasedOrdersFilterBarProps) {
    return (
        <div className="mb-3 flex flex-wrap items-center gap-3">
            <div className="w-full max-w-xs">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search Orders.."
                        className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                    />
                </div>
            </div>

            <div className="flex flex-1 flex-wrap items-center justify-end gap-2">
                <DatePicker
                    id="po-date-range"
                    mode="range"
                    defaultDate={[
                        startDate ? startDate : undefined,
                        endDate ? endDate : undefined,
                    ].filter(Boolean) as (string | Date)[]}
                    onChange={(dates: Date[]) => {
                        if (dates.length === 2) {
                            const [from, to] = dates;
                            const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                            onDateChange(fmt(from), fmt(to));
                        } else if (dates.length === 1) {
                            const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                            onDateChange(fmt(dates[0]), '');
                        } else {
                            onDateChange('', '');
                        }
                    }}
                    placeholder="Select range"
                />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="inline-flex h-9 min-w-[120px] items-center justify-between gap-2 rounded-[10px] border border-black/6 bg-white px-2.5 text-xs text-gray-500 outline-none transition-colors hover:bg-black/2 focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5"
                        >
                            <span className="inline-flex items-center gap-1.5">
                                <Filter className="h-3.5 w-3.5 text-gray-400" />
                                <span>{statusFilterLabel}</span>
                            </span>
                            <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-[170px] p-1.5">
                        <DropdownMenuItem
                            className="text-gray-500 dark:text-gray-300"
                            onClick={() => setStatusFilter('all')}
                        >
                            All Status
                        </DropdownMenuItem>
                        {statusOptions.map((opt) => (
                            <DropdownMenuItem
                                key={opt.value}
                                className={statusOptionTextClass(opt.value)}
                                onClick={() => setStatusFilter(String(opt.value))}
                            >
                                {opt.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button
                    type="button"
                    onClick={onLoad}
                    aria-label="Load records"
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 rounded-[10px] border-black/6 bg-white text-gray-500 hover:bg-black/2 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                >
                    <RefreshCw className={['h-3.5 w-3.5', loading ? 'animate-spin' : ''].join(' ')} />
                </Button>
            </div>
        </div>
    );
}

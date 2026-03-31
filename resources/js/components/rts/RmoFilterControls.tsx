import { orderStatusConfig } from '@/components/rts/rmo-config';
import { Search } from 'lucide-react';

interface RmoFilterControlsProps {
    searchValue: string;
    onSearchChange: (v: string) => void;
    selectedStatus: string;
    onStatusChange: (v: string) => void;
}

export function RmoFilterControls({
    searchValue,
    onSearchChange,
    selectedStatus,
    onStatusChange,
}: RmoFilterControlsProps) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative w-60">
                <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                    placeholder="Search orders…"
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            <select
                value={selectedStatus}
                onChange={(e) => onStatusChange(e.target.value)}
                className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
            >
                <option value="">All Statuses</option>
                {Object.keys(orderStatusConfig).map((status) => (
                    <option key={status} value={status}>
                        {status}
                    </option>
                ))}
            </select>
        </div>
    );
}

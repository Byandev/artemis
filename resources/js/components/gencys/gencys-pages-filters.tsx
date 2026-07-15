import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Search } from 'lucide-react';

/** Sentinel for "no filter" — Radix Select disallows an empty-string value. */
const ALL = '__all__';

interface Props {
    search: string;
    onSearchChange: (value: string) => void;
    status: string | undefined;
    onStatusChange: (value: string | undefined) => void;
    statuses: string[];
    platform: string | undefined;
    onPlatformChange: (value: string | undefined) => void;
    platforms: string[];
}

const triggerClass =
    'h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 font-mono! text-[12px]! sm:w-44 dark:border-white/6 dark:bg-zinc-800';

export default function GencysPagesFilters({
    search,
    onSearchChange,
    status,
    onStatusChange,
    statuses,
    platform,
    onPlatformChange,
    platforms,
}: Props) {
    return (
        <div className="mt-4 mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div className="relative w-full sm:w-72">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                    placeholder="Search name, owner, intern or brand…"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    aria-label="Search pages"
                />
            </div>

            <Select
                value={status ?? ALL}
                onValueChange={(v) => onStatusChange(v === ALL ? undefined : v)}
            >
                <SelectTrigger
                    className={triggerClass}
                    aria-label="Filter by status"
                >
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All statuses</SelectItem>
                    {statuses.map((value) => (
                        <SelectItem key={value} value={value}>
                            {value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={platform ?? ALL}
                onValueChange={(v) =>
                    onPlatformChange(v === ALL ? undefined : v)
                }
            >
                <SelectTrigger
                    className={triggerClass}
                    aria-label="Filter by platform"
                >
                    <SelectValue placeholder="All platforms" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All platforms</SelectItem>
                    {platforms.map((value) => (
                        <SelectItem key={value} value={value}>
                            {value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
    Boxes,
    type LucideIcon,
    PackageX,
    RotateCcw,
    ShoppingCart,
    Tag,
} from 'lucide-react';
import { useInventoryStat } from './use-inventory-stat';

interface Tile {
    /** Endpoint segment under .../inventory/dashboard/kpi/. */
    endpoint: string;
    label: string;
    icon: LucideIcon;
    sub: string;
    accentDot?: string;
    /** Warning tiles only colour their value/dot when the count is non-zero. */
    warn?: boolean;
}

const TILES: Tile[] = [
    {
        endpoint: 'inventory-items',
        label: 'Inventory Items',
        icon: Tag,
        sub: 'tracked items',
    },
    {
        endpoint: 'total-stocks',
        label: 'Total Stocks',
        icon: Boxes,
        sub: 'units on hand',
    },
    {
        endpoint: 'unfulfilled',
        label: 'Unfulfilled',
        icon: PackageX,
        sub: 'units still owed',
        accentDot: 'bg-amber-500',
        warn: true,
    },
    {
        endpoint: 'open-pos',
        label: 'Open POs',
        icon: ShoppingCart,
        sub: 'not yet delivered',
    },
];

/** Compact, locale-aware number; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

export default function KpiCards({ slug }: { slug: string }) {
    return (
        // Tighter between columns than between rows, matching the paired tables
        // below: side-by-side cards sit closer than stacked ones.
        <div className="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
            {TILES.map((tile) => (
                <KpiTile key={tile.endpoint} slug={slug} tile={tile} />
            ))}
        </div>
    );
}

/**
 * One tile, one request. Each fetches independently so a slow statistic never
 * holds up the rest of the row.
 */
function KpiTile({ slug, tile }: { slug: string; tile: Tile }) {
    const { data, loading, error, refetch } = useInventoryStat<{
        value: number;
    }>(slug, `kpi/${tile.endpoint}`);
    const value = data?.value ?? null;
    const Icon = tile.icon;
    const active = !tile.warn || (value ?? 0) > 0;

    return (
        // Deeper bottom padding than the sides: the value and its caption sit
        // low in the tile, and matching all four edges left them crowded
        // against the border.
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] pb-6 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {tile.label}
                </p>
                <div className="rounded-lg bg-stone-100 p-2 text-gray-700 dark:bg-zinc-800 dark:text-white/90">
                    <Icon className="h-5 w-5" />
                </div>
            </div>

            <div className="mt-4">
                {loading ? (
                    <>
                        <Skeleton className="h-[26px] w-16" />
                        <Skeleton className="mt-2 h-3 w-24" />
                    </>
                ) : error ? (
                    <>
                        <h4 className="font-mono text-[22px] font-semibold tracking-tight text-gray-300 tabular-nums dark:text-gray-600">
                            —
                        </h4>
                        <button
                            type="button"
                            onClick={refetch}
                            className="mt-1.5 flex items-center gap-1.5 text-[11px] text-red-500 hover:underline dark:text-red-400"
                        >
                            <RotateCcw className="h-3 w-3" />
                            Failed — retry
                        </button>
                    </>
                ) : (
                    <>
                        <h4
                            className={cn(
                                'font-mono text-[22px] font-semibold tracking-tight tabular-nums',
                                active && tile.warn
                                    ? 'text-red-500 dark:text-red-400'
                                    : 'text-gray-900 dark:text-gray-100',
                            )}
                        >
                            {num(value)}
                        </h4>
                        <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                            {tile.accentDot && (
                                <span
                                    className={cn(
                                        'h-1.5 w-1.5 shrink-0 rounded-full',
                                        active
                                            ? tile.accentDot
                                            : 'bg-gray-300 dark:bg-gray-600',
                                    )}
                                />
                            )}
                            {tile.sub}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}

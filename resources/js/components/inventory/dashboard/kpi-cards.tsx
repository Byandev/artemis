import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    Boxes,
    CalendarClock,
    type LucideIcon,
    PackageCheck,
    PackageX,
    ShoppingCart,
    Tag,
    TrendingDown,
} from 'lucide-react';
import { type InventoryKpis } from './types';
import { num } from './utils';

interface Tile {
    key: keyof InventoryKpis;
    label: string;
    icon: LucideIcon;
    sub: string;
    accentDot?: string;
    /** Warning tiles only colour their value/dot when the count is non-zero. */
    warn?: boolean;
}

const TILES: Tile[] = [
    {
        key: 'active_skus',
        label: 'Active SKUs',
        icon: Tag,
        sub: 'tracked items',
    },
    {
        key: 'total_stock_on_hand',
        label: 'Stock on Hand',
        icon: Boxes,
        sub: 'units in stock',
    },
    {
        key: 'low_stock_count',
        label: 'Low / Out of Stock',
        icon: TrendingDown,
        sub: 'below lead-time cover',
        accentDot: 'bg-red-500',
        warn: true,
    },
    {
        key: 'reorder_needed_count',
        label: 'Reorder Needed',
        icon: AlertTriangle,
        sub: 'need a PO',
        accentDot: 'bg-amber-500',
        warn: true,
    },
    {
        key: 'incoming_units',
        label: 'Incoming Units',
        icon: PackageCheck,
        sub: 'awaiting delivery',
        accentDot: 'bg-blue-500',
    },
    {
        key: 'open_pos',
        label: 'Open POs',
        icon: ShoppingCart,
        sub: 'not yet delivered',
    },
    {
        key: 'overdue_pos',
        label: 'Overdue POs',
        icon: CalendarClock,
        sub: 'past expected date',
        accentDot: 'bg-red-500',
        warn: true,
    },
    {
        key: 'shrinkage_units',
        label: 'Shrinkage',
        icon: PackageX,
        sub: 'bad + lost (period)',
        accentDot: 'bg-red-500',
        warn: true,
    },
];

export default function KpiCards({ kpis }: { kpis: InventoryKpis }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {TILES.map((tile) => {
                const value = kpis[tile.key];
                const active = !tile.warn || value > 0;
                const Icon = tile.icon;
                return (
                    <div
                        key={tile.key}
                        className="rounded-[14px] border border-black/6 bg-white p-[18px] transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                                {tile.label}
                            </p>
                            <div className="rounded-lg bg-stone-100 p-2 text-gray-700 dark:bg-zinc-800 dark:text-white/90">
                                <Icon className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3">
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
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

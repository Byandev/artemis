import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
    ClipboardList,
    Clock,
    type LucideIcon,
    PackageX,
    RotateCcw,
    Truck,
} from 'lucide-react';
import { PanelHelp } from './po-flow/shared';
import { useInventoryStat } from './use-inventory-stat';

interface KpiData {
    unfulfilled: number;
    not_ordered: number;
    stuck_inside: number;
    stuck_overdue: number;
    shippable_now: number;
    shippable_skus: number;
    open_total: number;
    sla_days: number;
    picking_days: number;
}

interface Tile {
    label: string;
    icon: LucideIcon;
    value: (d: KpiData) => number;
    sub: (d: KpiData) => string;
    /** What the number means and where it comes from, behind the ? icon. */
    help: React.ReactNode;
    /** Warning tiles only colour themselves when the number is non-zero. */
    tone?: 'warn' | 'bad';
}

/**
 * The four figures the rest of the page explains, in the order it explains
 * them: the outcome, then the three places stock can be stuck on its way to
 * fixing it.
 *
 * Replaces a row that counted SKUs and total stock — both true, neither
 * actionable. Nobody ever changed a decision because the item count moved.
 */
const TILES: Tile[] = [
    {
        label: 'Unfulfilled',
        icon: PackageX,
        value: (d) => d.unfulfilled,
        sub: () => 'units of demand not yet met',
        help: 'Orders taken that stock has not covered. This is the outcome every other panel on this page is trying to explain — if it falls, something upstream got better.',
        tone: 'bad',
    },
    {
        label: 'Not ordered yet',
        icon: ClipboardList,
        value: (d) => d.not_ordered,
        sub: () => 'units the reorder maths still wants',
        help: 'What to buy on top of everything already on order. Every open purchase order is credited against this first, at any stage — so nothing here is stock you have already ordered. Counted per item group, the way the items list shows it.',
        tone: 'warn',
    },
    {
        label: 'Stuck inside',
        icon: Clock,
        value: (d) => d.stuck_inside,
        sub: (d) =>
            d.stuck_overdue > 0
                ? `${d.stuck_overdue.toLocaleString('en-PH')} past the ${d.sla_days}-day target`
                : 'never sent to a supplier',
        help: 'Units on purchase orders that have been raised but not yet paid for — sitting in approval, or approved and queued for payment. The quantity is committed, so it will not be reordered; it just has not started moving.',
        tone: 'warn',
    },
    {
        label: 'Shippable now',
        icon: Truck,
        value: (d) => d.shippable_now,
        sub: (d) =>
            d.shippable_skus > 0
                ? `on the shelf across ${d.shippable_skus} SKU${d.shippable_skus === 1 ? '' : 's'}`
                : 'nothing on the shelf to ship',
        // Deliberately toneless: stock ready to go out is neither good news nor
        // bad on its own. Whether any of it has stalled is a separate question,
        // and the warehouse card answers it from despatch dates.
        help: 'Stock physically here with an unfulfilled order against it — everything that could leave today. Matched per SKU, since stock on one variant cannot ship an order placed against another. This counts what is ready, not what is late: the warehouse card is where stock that has stopped moving shows up.',
    },
];

/** Compact, locale-aware number; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

/**
 * One request for all four: they come from the same two scans server-side, so
 * splitting them into a call per tile would repeat that work four times.
 */
export default function KpiCards({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<KpiData>(
        slug,
        'po-flow/kpi',
    );

    return (
        <div className="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
            {TILES.map((tile) => (
                <KpiTile
                    key={tile.label}
                    tile={tile}
                    data={data}
                    loading={loading}
                    error={error}
                    onRetry={refetch}
                />
            ))}
        </div>
    );
}

function KpiTile({
    tile,
    data,
    loading,
    error,
    onRetry,
}: {
    tile: Tile;
    data: KpiData | null;
    loading: boolean;
    error: boolean;
    onRetry: () => void;
}) {
    const Icon = tile.icon;
    const value = data ? tile.value(data) : null;
    const active = (value ?? 0) > 0;

    return (
        // Deeper bottom padding than the sides: the value and its caption sit
        // low in the tile, and matching all four edges left them crowded
        // against the border.
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] pb-6 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-1.5">
                    <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        {tile.label}
                    </p>
                    <PanelHelp>{tile.help}</PanelHelp>
                </div>
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
                ) : error || !data ? (
                    <>
                        <h4 className="font-mono text-[22px] font-semibold tracking-tight text-gray-300 tabular-nums dark:text-gray-600">
                            —
                        </h4>
                        <button
                            type="button"
                            onClick={onRetry}
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
                                !active || !tile.tone
                                    ? 'text-gray-900 dark:text-gray-100'
                                    : tile.tone === 'bad'
                                      ? 'text-red-600 dark:text-red-400'
                                      : 'text-amber-600 dark:text-amber-500',
                            )}
                        >
                            {num(value)}
                        </h4>
                        <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                            <span
                                className={cn(
                                    'h-1.5 w-1.5 shrink-0 rounded-full',
                                    !active
                                        ? 'bg-emerald-500'
                                        : // A toneless tile is reporting, not
                                          // warning — it gets a neutral dot
                                          // rather than falling through to amber.
                                          !tile.tone
                                          ? 'bg-gray-300 dark:bg-gray-600'
                                          : tile.tone === 'bad'
                                            ? 'bg-red-500'
                                            : 'bg-amber-500',
                                )}
                            />
                            {tile.sub(data)}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}

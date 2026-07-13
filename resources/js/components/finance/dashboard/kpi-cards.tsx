import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    type LucideIcon,
    Scale,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { type FinanceKpis } from './types';

const money = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/**
 * Period-over-period change. `invert` flips the good/bad colouring for
 * out-flows, where an increase is the unfavourable direction.
 */
function DeltaBadge({
    value,
    invert = false,
}: {
    value: number | null;
    invert?: boolean;
}) {
    if (value === null) {
        return (
            <span className="font-mono text-[10px] text-gray-400">
                — vs prev
            </span>
        );
    }

    const up = value >= 0;
    const good = invert ? !up : up;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-0.5 font-mono text-[10px]',
                good ? 'text-emerald-500' : 'text-red-500',
            )}
        >
            {up ? (
                <ArrowUpRight className="h-3 w-3" />
            ) : (
                <ArrowDownRight className="h-3 w-3" />
            )}
            {Math.abs(value)}% vs prev
        </span>
    );
}

interface Tile {
    key: 'total_balance' | 'cash_in' | 'cash_out' | 'net_cash_flow';
    label: string;
    icon: LucideIcon;
    iconClass?: string;
    deltaKey?: keyof FinanceKpis['deltas'];
    invertDelta?: boolean;
}

const TILES: Tile[] = [
    { key: 'total_balance', label: 'Total Balance', icon: Wallet },
    {
        key: 'cash_in',
        label: 'Cash In',
        icon: ArrowUpRight,
        iconClass: 'text-emerald-500',
        deltaKey: 'cash_in',
    },
    {
        key: 'cash_out',
        label: 'Cash Out',
        icon: ArrowDownRight,
        iconClass: 'text-red-500',
        deltaKey: 'cash_out',
        invertDelta: true,
    },
    {
        key: 'net_cash_flow',
        label: 'Net Cash Flow',
        icon: TrendingUp,
        deltaKey: 'net_cash_flow',
    },
];

export default function KpiCards({ kpis }: { kpis: FinanceKpis }) {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            {TILES.map((tile) => {
                const Icon = tile.icon;

                return (
                    <div
                        key={tile.key}
                        className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
                    >
                        <div className="flex items-center gap-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            <Icon className={cn('h-4 w-4', tile.iconClass)} />
                            <span>{tile.label}</span>
                        </div>
                        <div className="mt-2 font-mono text-[20px] font-semibold text-gray-800 dark:text-gray-100">
                            {money(kpis[tile.key])}
                        </div>
                        {tile.deltaKey && (
                            <div className="mt-1">
                                <DeltaBadge
                                    value={kpis.deltas[tile.deltaKey]}
                                    invert={tile.invertDelta}
                                />
                            </div>
                        )}
                    </div>
                );
            })}

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                <div className="flex items-center gap-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    <AlertTriangle className="h-4 w-4 text-amber-500" />
                    <span>Unreconciled</span>
                </div>
                <div className="mt-2 font-mono text-[20px] font-semibold text-gray-800 dark:text-gray-100">
                    {kpis.unreconciled_count}
                </div>
                <div className="mt-1 flex items-center gap-1 font-mono text-[10px] text-gray-400">
                    <Scale className="h-3 w-3" />₱
                    {money(kpis.unreconciled_amount)}
                </div>
            </div>
        </div>
    );
}

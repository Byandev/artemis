import { cn } from '@/lib/utils';
import { Info } from 'lucide-react';
import { type Profitability } from './types';
import { money } from './utils';

/**
 * Cross-domain profitability tiles (revenue, ad spend, gross profit, margin,
 * MER). Deliberately surfaces the backend `assumptions` so the numbers are
 * never read as delivered-revenue / true net profit.
 */
export default function ProfitabilityTiles({ data }: { data: Profitability }) {
    const tiles = [
        { label: 'Revenue', value: `₱${money(data.revenue)}` },
        { label: 'Ad Spend', value: `₱${money(data.ad_spend)}` },
        {
            label: 'Gross Profit',
            value: `₱${money(data.gross_profit)}`,
            accent:
                data.gross_profit >= 0
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-red-500',
        },
        {
            label: 'Margin',
            value: data.margin_pct === null ? '—' : `${data.margin_pct}%`,
        },
        {
            label: 'MER',
            value: data.mer === null ? '—' : `${data.mer}×`,
        },
    ];

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                {tiles.map((t) => (
                    <div
                        key={t.label}
                        className="rounded-[12px] border border-black/6 bg-stone-50 p-3 dark:border-white/6 dark:bg-zinc-800"
                    >
                        <div className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                            {t.label}
                        </div>
                        <div
                            className={cn(
                                'mt-1.5 font-mono text-[17px] font-semibold text-gray-800 dark:text-gray-100',
                                t.accent,
                            )}
                        >
                            {t.value}
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex items-start gap-1.5 font-mono text-[10px] leading-relaxed text-gray-400">
                <Info className="mt-0.5 h-3 w-3 shrink-0" />
                <span>{data.assumptions.join(' ')}</span>
            </div>
        </div>
    );
}

import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';

interface Props {
    label: string;
    mine: number;
    avg: number;
    fmt: (v: number) => string;
    invert?: boolean;
}

export default function PerfRow({ label, mine, avg, fmt, invert = false }: Props) {
    const diff = avg === 0 ? 0 : ((mine - avg) / avg) * 100;
    const isUp = diff > 0;
    const isBetter = invert ? !isUp : isUp;
    const DiffIcon = diff === 0 ? Minus : isUp ? ArrowUp : ArrowDown;

    return (
        <div className="grid grid-cols-[1fr_auto_auto] items-center gap-4 border-t border-black/4 px-5 py-3 dark:border-white/4">
            <p className="text-[12px] font-medium text-gray-600 dark:text-gray-400">{label}</p>
            <div className="flex items-center gap-2 text-right">
                <p className="font-mono text-[12px] font-semibold text-gray-800 tabular-nums dark:text-gray-200">
                    {fmt(mine)}
                </p>
                {avg !== 0 && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                            isBetter
                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400'
                                : diff === 0
                                  ? 'bg-gray-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500'
                                  : 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400',
                        )}
                    >
                        <DiffIcon className="h-2.5 w-2.5" />
                        {Math.abs(diff).toFixed(0)}%
                    </span>
                )}
            </div>
            <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                avg {fmt(avg)}
            </p>
        </div>
    );
}

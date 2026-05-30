import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

interface Props {
    label: string;
    value: string | number;
    sub?: string;
    icon: LucideIcon;
    iconBg: string;
    iconColor: string;
    progress?: number;
    badge?: { text: string; color: 'green' | 'red' | 'amber' | 'gray' };
}

export default function KpiCard({ label, value, sub, icon: Icon, iconBg, iconColor, progress, badge }: Props) {
    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-start justify-between">
                <div className={cn('rounded-xl p-2.5', iconBg)}>
                    <Icon className={cn('h-4 w-4', iconColor)} />
                </div>
                {badge && (
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                            badge.color === 'green' && 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                            badge.color === 'red' && 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400',
                            badge.color === 'amber' && 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                            badge.color === 'gray' && 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                        )}
                    >
                        {badge.text}
                    </span>
                )}
            </div>
            <div>
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">{label}</p>
                <p className="mt-0.5 font-mono text-2xl font-bold tracking-tight text-gray-900 tabular-nums dark:text-white">
                    {value}
                </p>
                {sub && <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">{sub}</p>}
            </div>
            {progress !== undefined && (
                <div className="h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                    <div
                        className={cn(
                            'h-full rounded-full transition-all',
                            progress >= 80 ? 'bg-emerald-500' : progress >= 50 ? 'bg-amber-400' : 'bg-red-400',
                        )}
                        style={{ width: `${Math.min(100, progress)}%` }}
                    />
                </div>
            )}
        </div>
    );
}

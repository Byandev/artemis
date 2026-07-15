import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

type Accent = 'brand' | 'blue' | 'amber' | 'violet';

const ACCENTS: Record<Accent, string> = {
    brand: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
    blue: 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
    amber: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
    violet: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
};

interface Props {
    label: string;
    value: ReactNode;
    sub?: ReactNode;
    icon: LucideIcon;
    accent?: Accent;
}

/**
 * A single KPI tile for the client report. Kept generic so the report grid is a
 * flat list of tiles rather than bespoke markup per metric.
 */
export default function StatCard({
    label,
    value,
    sub,
    icon: Icon,
    accent = 'brand',
}: Props) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div className="flex items-start justify-between gap-3">
                <span className="text-[11px] font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                    {label}
                </span>
                <span
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${ACCENTS[accent]}`}
                >
                    <Icon className="h-4 w-4" />
                </span>
            </div>
            <div className="mt-3 font-mono text-2xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-zinc-100">
                {value}
            </div>
            {sub && (
                <div className="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {sub}
                </div>
            )}
        </div>
    );
}

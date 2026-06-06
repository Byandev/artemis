import {
    Clapperboard,
    Clock,
    FileImage,
    LayoutGrid,
    Pencil,
    TrendingUp,
} from 'lucide-react';
import { ReactNode } from 'react';
import { Kpis } from './types';

function KpiCard({
    icon,
    label,
    value,
    sub,
    accentDot,
}: {
    icon: ReactNode;
    label: string;
    value: number;
    sub?: ReactNode;
    accentDot?: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {label}
                </p>
                <div className="rounded-lg bg-stone-100 p-2 text-gray-700 dark:bg-zinc-800 dark:text-white/90">
                    {icon}
                </div>
            </div>
            <div className="mt-3">
                <h4 className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                    {value}
                </h4>
                {sub && (
                    <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                        {accentDot && (
                            <span
                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${accentDot}`}
                            />
                        )}
                        {sub}
                    </p>
                )}
            </div>
        </div>
    );
}

/** Top-row summary metrics for the editor's board. */
export default function KpiCards({ kpis }: { kpis: Kpis }) {
    return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <KpiCard
                icon={<LayoutGrid className="h-5 w-5" />}
                label="Total Creatives"
                value={kpis.total}
                sub={
                    <span className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <Clapperboard className="h-3 w-3" /> {kpis.video}
                        </span>
                        <span className="flex items-center gap-1">
                            <FileImage className="h-3 w-3" /> {kpis.image}
                        </span>
                    </span>
                }
            />
            <KpiCard
                icon={<Clock className="h-5 w-5" />}
                label="Awaiting Review"
                value={kpis.awaiting_review}
                accentDot="bg-blue-500"
                sub="for approval / re-approval"
            />
            <KpiCard
                icon={<Pencil className="h-5 w-5" />}
                label="Needs Revision"
                value={kpis.needs_revision}
                accentDot="bg-amber-500"
                sub="your action queue"
            />
            <KpiCard
                icon={<TrendingUp className="h-5 w-5" />}
                label="Approved"
                value={kpis.approved}
                accentDot="bg-emerald-500"
                sub={`${kpis.approval_rate}% approval rate`}
            />
        </div>
    );
}

import { Pipeline } from './types';

const STAGES: { key: keyof Pipeline; label: string; color: string }[] = [
    { key: 'for_approval', label: 'For Approval', color: 'bg-blue-500' },
    { key: 'revision', label: 'Revision', color: 'bg-amber-500' },
    { key: 'approved', label: 'Approved', color: 'bg-emerald-500' },
    { key: 'running_ads', label: 'Running in Ads', color: 'bg-orange-500' },
];

/** Horizontal bar funnel across the creative lifecycle stages. */
export default function PipelineFunnel({ pipeline }: { pipeline: Pipeline }) {
    const max = Math.max(1, ...STAGES.map((s) => pipeline[s.key]));

    return (
        <div className="space-y-2.5">
            {STAGES.map((s) => {
                const value = pipeline[s.key];
                return (
                    <div key={s.key} className="flex items-center gap-3">
                        <span className="w-28 shrink-0 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                            {s.label}
                        </span>
                        <div className="h-5 flex-1 overflow-hidden rounded-md bg-stone-100 dark:bg-zinc-800">
                            <div
                                className={`h-full rounded-md ${s.color} transition-all`}
                                style={{
                                    width: `${Math.max(value > 0 ? 6 : 0, (value / max) * 100)}%`,
                                }}
                            />
                        </div>
                        <span className="w-8 shrink-0 text-right font-mono text-[12px] font-semibold text-gray-700 tabular-nums dark:text-gray-200">
                            {value}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

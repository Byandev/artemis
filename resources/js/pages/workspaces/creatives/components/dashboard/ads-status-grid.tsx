import { AdsStatus } from '../../types';

const STATS: { key: AdsStatus; label: string; dot: string }[] = [
    { key: 'running', label: 'Running', dot: 'bg-emerald-500' },
    { key: 'scale', label: 'Scale', dot: 'bg-orange-500' },
    { key: 'pending', label: 'Pending', dot: 'bg-gray-400' },
    { key: 'kill', label: 'Kill', dot: 'bg-red-500' },
];

/** 2×2 grid of ads-lifecycle counts. */
export default function AdsStatusGrid({
    ads,
}: {
    ads: Record<AdsStatus, number>;
}) {
    return (
        <div className="grid grid-cols-2 gap-3">
            {STATS.map((s) => (
                <div
                    key={s.key}
                    className="rounded-xl bg-stone-50 px-3 py-2.5 dark:bg-zinc-800/50"
                >
                    <div className="flex items-center gap-1.5">
                        <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
                        <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                            {s.label}
                        </span>
                    </div>
                    <p className="mt-1 font-mono text-[20px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                        {ads[s.key]}
                    </p>
                </div>
            ))}
        </div>
    );
}

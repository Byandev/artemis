export interface DistributionBucket {
    key: string;
    label: string;
    count: number;
    /** Percentage of rated teams, already rounded. */
    share: number;
}

export interface DistributionData {
    buckets: DistributionBucket[];
    /** Teams with a target to be measured against. */
    total: number;
    /** Teams carrying only an ad budget — no achievement to band them by. */
    unrated: number;
}

/**
 * Status ramp, not a categorical palette: these bands are ordered good → bad, so
 * they wear the status colours and always ship with their label and count. The
 * colour is never the only thing saying which band is which.
 */
const bandColor: Record<string, { fill: string; swatch: string }> = {
    at_target: { fill: '#10d3a1', swatch: 'bg-brand-500' },
    near: { fill: '#eab308', swatch: 'bg-yellow-500' },
    half: { fill: '#f97316', swatch: 'bg-orange-500' },
    below: { fill: '#ef4444', swatch: 'bg-red-500' },
};

const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * How the teams are spread across achievement bands. A donut earns its place
 * here because the parts genuinely sum to one whole — every rated team sits in
 * exactly one band — and the count that matters most is read from the middle.
 */
export function GameboardDistribution({ data }: { data: DistributionData }) {
    const { buckets, total, unrated } = data;

    // Arcs are laid end to end around the ring, each starting where the last
    // finished.
    let offset = 0;

    return (
        <div className="h-full rounded-[12px] border border-black/6 bg-white px-3 py-2.5 dark:border-white/8 dark:bg-zinc-900">
            <h2 className="my-0! font-mono text-[10px]! font-medium tracking-[0.16em] text-gray-700 uppercase dark:text-gray-200">
                Achievement Distribution
            </h2>

            <div className="mt-3 flex items-center gap-4">
                <div className="relative h-28 w-28 shrink-0">
                    <svg viewBox="0 0 100 100" className="h-full w-full">
                        <circle
                            cx="50"
                            cy="50"
                            r={RADIUS}
                            fill="none"
                            strokeWidth="12"
                            className="stroke-stone-200 dark:stroke-zinc-800"
                        />
                        {total > 0 &&
                            buckets.map((bucket) => {
                                if (bucket.count === 0) return null;

                                const fraction = bucket.count / total;
                                const dash = fraction * CIRCUMFERENCE;
                                const start = offset;
                                offset += dash;

                                return (
                                    <circle
                                        key={bucket.key}
                                        cx="50"
                                        cy="50"
                                        r={RADIUS}
                                        fill="none"
                                        strokeWidth="12"
                                        stroke={bandColor[bucket.key]?.fill}
                                        strokeDasharray={`${dash} ${CIRCUMFERENCE - dash}`}
                                        strokeDashoffset={-start}
                                        // 12 o'clock start, clockwise, like a clock face.
                                        transform="rotate(-90 50 50)"
                                    >
                                        <title>{`${bucket.label}: ${bucket.count} of ${total}`}</title>
                                    </circle>
                                );
                            })}
                    </svg>

                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-[22px] leading-none font-bold text-gray-900 tabular-nums dark:text-white">
                            {total}
                        </span>
                        <span className="font-mono text-[8px] tracking-[0.14em] text-gray-500 uppercase dark:text-gray-400">
                            {total === 1 ? 'Team' : 'Teams'}
                        </span>
                    </div>
                </div>

                <ul className="min-w-0 flex-1 space-y-1.5">
                    {buckets.map((bucket) => (
                        <li
                            key={bucket.key}
                            className="flex items-center gap-2 text-[11px]"
                        >
                            <span
                                className={`h-2.5 w-2.5 shrink-0 rounded-[3px] ${bandColor[bucket.key]?.swatch}`}
                            />
                            <span className="flex-1 truncate text-gray-700 dark:text-gray-200">
                                {bucket.label}
                            </span>
                            <span className="shrink-0 font-mono text-gray-500 tabular-nums dark:text-gray-400">
                                {bucket.count} ({bucket.share}%)
                            </span>
                        </li>
                    ))}
                </ul>
            </div>

            {unrated > 0 && (
                <p className="mt-2 text-[10px] text-gray-400 dark:text-gray-500">
                    {unrated} {unrated === 1 ? 'team has' : 'teams have'} no
                    sales target to be measured against.
                </p>
            )}
        </div>
    );
}

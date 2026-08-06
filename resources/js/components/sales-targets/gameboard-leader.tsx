import { TeamPerformance } from '@/components/sales-targets/gameboard-teams';
import { formatPeso } from '@/pages/workspaces/sales-targets/shared';
import { Square, SquareCheck, Trophy } from 'lucide-react';
import { useId } from 'react';

/** Rank 1's row, plus what the leader endpoint adds on top of it. */
export interface LeaderTeam extends TeamPerformance {
    /** Daily sales over the fortnight ending on the board's day. */
    trend: { date: string; sales: number }[];
    /** The ROAS bar this target judges teams against. */
    qualifying_roas: number;
}

/**
 * A sparkline of the leader's recent sales: shape only, no axes or grid — the
 * exact figures are already on the tiles above. The line is one series, so it
 * needs no legend; the heading beside it names what it shows.
 *
 * Stroke steps down to brand-700 in light mode: brand-500 on white is a 1.9:1
 * contrast, which a 2px line disappears into.
 */
function Sparkline({ points }: { points: number[] }) {
    const gradientId = useId();

    if (points.length < 2) {
        return null;
    }

    const width = 320;
    const height = 56;
    const pad = 4;

    const max = Math.max(...points);
    const min = Math.min(...points);
    const span = max - min || 1;

    const x = (i: number) => (i / (points.length - 1)) * width;
    const y = (value: number) =>
        // A flat series sits mid-height rather than pinned to the floor.
        max === min
            ? height / 2
            : height - pad - ((value - min) / span) * (height - pad * 2);

    // Smooth with horizontal control points: no overshoot, no false wiggles.
    const line = points
        .map((value, i) => {
            if (i === 0) return `M ${x(0)} ${y(value)}`;

            const cx = (x(i - 1) + x(i)) / 2;

            return `C ${cx} ${y(points[i - 1])} ${cx} ${y(value)} ${x(i)} ${y(value)}`;
        })
        .join(' ');

    const lastX = x(points.length - 1);
    const lastY = y(points[points.length - 1]);

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            preserveAspectRatio="none"
            className="h-14 w-full text-brand-700 dark:text-brand-400"
            role="img"
            aria-label={`Leader's daily sales over the last ${points.length} days, ${formatPeso(min)} to ${formatPeso(max)}`}
        >
            <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stopColor="currentColor"
                        stopOpacity="0.22"
                    />
                    <stop
                        offset="100%"
                        stopColor="currentColor"
                        stopOpacity="0"
                    />
                </linearGradient>
            </defs>

            <path
                d={`${line} L ${width} ${height} L 0 ${height} Z`}
                fill={`url(#${gradientId})`}
            />
            <path
                d={line}
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                vectorEffect="non-scaling-stroke"
            />
            {/* Baseline, recessive — it marks the floor without competing. */}
            <line
                x1="0"
                y1={height - 1}
                x2={width}
                y2={height - 1}
                stroke="currentColor"
                strokeWidth="1"
                strokeDasharray="3 4"
                strokeOpacity="0.3"
                vectorEffect="non-scaling-stroke"
            />
            <circle cx={lastX} cy={lastY} r="3.5" fill="currentColor" />
        </svg>
    );
}

function Criterion({ met, label }: { met: boolean; label: string }) {
    const Icon = met ? SquareCheck : Square;

    return (
        <div className="flex items-center gap-2">
            <Icon
                className={`h-3.5 w-3.5 shrink-0 ${
                    met
                        ? 'text-brand-600 dark:text-brand-400'
                        : 'text-gray-300 dark:text-gray-600'
                }`}
            />
            <span
                className={`truncate text-[11px] ${
                    met
                        ? 'text-gray-700 dark:text-gray-200'
                        : 'text-gray-400 dark:text-gray-500'
                }`}
            >
                {label}
            </span>
        </div>
    );
}

/**
 * Who is out front, what it takes to qualify, and the shape of the leader's run.
 * The ticks reflect the leader's own standing — they are the same two conditions
 * the Qualified Teams tile counts.
 */
export function GameboardLeader({
    leader,
    qualifyingRoas,
}: {
    leader: LeaderTeam;
    qualifyingRoas: number;
}) {
    return (
        <div className="mt-2 grid gap-4 rounded-[12px] border border-black/6 bg-white px-4 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)] md:gap-6 dark:border-white/8 dark:bg-zinc-900">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-amber-500/10 text-amber-500">
                    <Trophy className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                    <p className="font-mono text-[9px] font-medium tracking-[0.14em] text-amber-600 uppercase dark:text-amber-500">
                        Current Leader
                    </p>
                    <p className="truncate text-[18px] leading-tight font-bold tracking-tight text-brand-600 uppercase dark:text-brand-400">
                        {leader.name}
                    </p>
                    <p className="truncate text-[10px] text-gray-500 dark:text-gray-400">
                        Top ranked team
                    </p>
                </div>
            </div>

            <div className="min-w-0 md:border-l md:border-black/6 md:pl-6 md:dark:border-white/8">
                <p className="font-mono text-[9px] font-medium tracking-[0.14em] text-gray-500 uppercase dark:text-gray-400">
                    Qualification Criteria
                </p>
                <div className="mt-2 space-y-1.5">
                    <Criterion
                        met={leader.hit_target}
                        label="100%+ Sales Target Achievement"
                    />
                    <Criterion
                        met={leader.hit_roas}
                        label={`${qualifyingRoas.toFixed(2)}+ ROAS`}
                    />
                </div>
            </div>

            <div className="flex min-w-0 items-center">
                <Sparkline points={leader.trend.map((point) => point.sales)} />
            </div>
        </div>
    );
}

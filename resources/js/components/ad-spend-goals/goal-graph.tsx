import { cn, currencyFormatter } from '@/lib/utils';
import { Check, X } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

export interface GoalStatus {
    daily_target: number;
    start_date: string;
    end_date: string;
    total_days: number;
    days_elapsed: number;
    days_remaining: number;
    days_hit: number;
    recent_date: string | null;
    recent_spend: number;
    hit_recent: boolean;
    peak_date: string | null;
    peak_spend: number;
    hit_ever: boolean;
    is_ended: boolean;
    status: 'upcoming' | 'on_target' | 'slipping' | 'below';
    // Ad spend on the goal's start date (0 if unrecorded). The yesterday-short,
    // ramp increment, and increase-since-start are derived on the frontend.
    starting_spend: number;
    // Optional stepping-stone thresholds under the target, ascending.
    milestones: Milestone[];
    // Optional per-member target slices with their actual spend.
    members: GoalMember[];
}

export interface Milestone {
    id: number;
    amount: number;
    label: string | null;
    reached: boolean;
    reached_date: string | null;
}

export interface GoalMember {
    user_id: number;
    name: string | null;
    daily_target: number;
    starting_spend: number;
    recent_spend: number;
    peak_spend: number;
    reached: boolean;
}

export interface Goal {
    id: number;
    team_id: number;
    team_name: string | null;
    daily_target: number;
    start_date: string;
    end_date: string;
    status: GoalStatus;
}

export interface TeamOption {
    id: number;
    name: string;
    members?: { id: number; name: string }[];
}

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** Format a 'YYYY-MM-DD' string as 'Mon D' without timezone drift. */
export function fmtDate(date: string | null): string {
    if (!date) return '—';
    const [, m, d] = date.split('-').map(Number);
    return `${MONTHS[m - 1]} ${d}`;
}

export const STATUS_META: Record<string, { label: string; className: string }> =
    {
        on_target: {
            label: 'On target',
            className:
                'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
        },
        slipping: {
            label: 'Slipping',
            className:
                'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        },
        below: {
            label: 'Below target',
            className:
                'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        },
        upcoming: {
            label: 'Upcoming',
            className:
                'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
        },
    };

// Artemis brand teal for "hit", warning amber for "below" — used by the SVG
// donut and CSS-gradient mini-bars (hex, since gradients can't use tokens).
export const BRAND_GRAD = 'linear-gradient(90deg,#10d3a1,#0eaa82)';
export const WARN_GRAD = 'linear-gradient(90deg,#fdb022,#dc6803)';

/**
 * The reference day used everywhere: the higher of yesterday vs the peak day.
 * The peak is the max daily spend, so it wins unless yesterday IS the peak
 * (ties → labelled "yesterday").
 */
export function bestOf(s: GoalStatus) {
    const hasData = s.peak_date !== null;
    const fromYesterday = s.recent_spend >= s.peak_spend;
    const spend = Math.max(s.recent_spend, s.peak_spend);
    const date = fromYesterday ? s.recent_date : s.peak_date;
    const label = fromYesterday ? 'Best · yesterday' : 'Best · peak day';
    const hit = hasData && spend >= s.daily_target;
    const pct = s.daily_target > 0 ? (spend / s.daily_target) * 100 : 0;
    return { hasData, spend, date, label, hit, pct };
}

/**
 * Progress donut: the best day as a share of the daily target. A gradient of
 * reserved status hues — emerald when hit, amber when below — over a neutral
 * track, animating up on mount. The ✓/✗ shown alongside it (by callers) carries
 * the same meaning so status is never colour-alone.
 */
export function GoalDonut({
    pct,
    hit,
    hasData,
    size = 84,
    markers = [],
}: {
    pct: number;
    hit: boolean;
    hasData: boolean;
    size?: number;
    /** Tick marks on the ring, e.g. milestones (% of target). */
    markers?: { pct: number; reached: boolean }[];
}) {
    // r chosen so the circumference ≈ 100 → the dash length is the percentage.
    const R = 15.9155;
    const target = Math.max(0, Math.min(100, pct));
    const gid = useId().replace(/:/g, '');
    const [arc, setArc] = useState(0);

    useEffect(() => {
        const raf = requestAnimationFrame(() => setArc(target));
        return () => cancelAnimationFrame(raf);
    }, [target]);

    const from = hit ? '#10d3a1' : '#fdb022'; // brand-500 / warning-400
    const to = hit ? '#0eaa82' : '#dc6803'; // brand-600 / warning-600

    return (
        <div
            className="relative shrink-0"
            style={{ width: size, height: size }}
        >
            <svg viewBox="0 0 36 36" className="h-full w-full -rotate-90">
                <defs>
                    <linearGradient
                        id={`ring-${gid}`}
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                    >
                        <stop offset="0%" stopColor={from} />
                        <stop offset="100%" stopColor={to} />
                    </linearGradient>
                </defs>
                <circle
                    cx="18"
                    cy="18"
                    r={R}
                    fill="none"
                    strokeWidth="3"
                    className="stroke-stone-100 dark:stroke-zinc-800"
                />
                {hasData && (
                    <circle
                        cx="18"
                        cy="18"
                        r={R}
                        fill="none"
                        stroke={`url(#ring-${gid})`}
                        strokeWidth="3"
                        strokeLinecap="round"
                        strokeDasharray={`${arc} ${100 - arc}`}
                        style={{
                            transition:
                                'stroke-dasharray 0.9s cubic-bezier(0.22, 1, 0.36, 1)',
                        }}
                    />
                )}
                {/* Milestone ticks — same clockwise-from-top frame as the arc. */}
                {markers.map((mk, i) => {
                    const a = (Math.min(100, mk.pct) / 100) * 2 * Math.PI;
                    const cos = Math.cos(a);
                    const sin = Math.sin(a);
                    return (
                        <line
                            key={i}
                            x1={18 + 13.6 * cos}
                            y1={18 + 13.6 * sin}
                            x2={18 + 18.4 * cos}
                            y2={18 + 18.4 * sin}
                            strokeWidth="0.9"
                            strokeLinecap="round"
                            stroke={mk.reached ? '#0d8264' : '#cbd5e1'}
                        />
                    );
                })}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span
                    className="font-mono leading-none font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                    style={{ fontSize: size * 0.2 }}
                >
                    {hasData ? `${Math.round(pct)}%` : '—'}
                </span>
                <span
                    className="mt-1 font-mono tracking-wider text-gray-400 uppercase dark:text-gray-500"
                    style={{ fontSize: Math.max(7, size * 0.075) }}
                >
                    of target
                </span>
            </div>
        </div>
    );
}

/** One labelled metric with a % pill and a gradient mini-bar (capped at 100%). */
function MetricBlock({
    label,
    date,
    value,
    target,
    hit,
    big,
}: {
    label: string;
    date: string | null;
    value: number;
    target: number;
    hit: boolean;
    big?: boolean;
}) {
    const has = date !== null;
    const pct = target > 0 ? (value / target) * 100 : 0;
    const bar = Math.max(0, Math.min(100, pct));

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    {label}
                    {has && (
                        <span className="normal-case"> · {fmtDate(date)}</span>
                    )}
                </p>
                {has && (
                    <span
                        className={cn(
                            'flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold tabular-nums',
                            hit
                                ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'
                                : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                        )}
                    >
                        {Math.round(pct)}%
                        {hit ? (
                            <Check className="h-3 w-3" />
                        ) : (
                            <X className="h-3 w-3" />
                        )}
                    </span>
                )}
            </div>
            <p
                className={cn(
                    'mt-1 font-mono font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-50',
                    big
                        ? 'text-[32px] leading-none'
                        : 'text-[19px] leading-none',
                )}
            >
                {has ? currencyFormatter(value) : '—'}
            </p>
            <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                {has && (
                    <div
                        className="h-full rounded-full"
                        style={{
                            width: `${bar}%`,
                            background: hit ? BRAND_GRAD : WARN_GRAD,
                        }}
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Premium hero for the detail page: a glowing gradient donut beside two metric
 * blocks — the best day (big) and yesterday — each with its own progress bar.
 */
export function GoalGraph({
    status,
    size = 176,
}: {
    status: GoalStatus;
    size?: number;
}) {
    const s = status;
    const b = bestOf(s);
    const glow = b.hit ? '#10d3a1' : '#f79009'; // brand-500 / warning-500

    const markers = s.daily_target
        ? s.milestones.map((m) => ({
              pct: (m.amount / s.daily_target) * 100,
              reached: m.reached,
          }))
        : [];

    return (
        <div className="flex flex-col items-center gap-10 sm:flex-row sm:gap-12">
            <div className="relative shrink-0">
                {b.hasData && (
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-3 rounded-full blur-2xl"
                        style={{ backgroundColor: glow, opacity: 0.18 }}
                    />
                )}
                <GoalDonut
                    pct={b.pct}
                    hit={b.hit}
                    hasData={b.hasData}
                    size={size}
                    markers={markers}
                />
            </div>

            <div className="w-full flex-1 space-y-5">
                <div>
                    <MetricBlock
                        big
                        label={b.label}
                        date={b.date}
                        value={b.spend}
                        target={s.daily_target}
                        hit={b.hit}
                    />
                    <p className="mt-2 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                        of {currencyFormatter(s.daily_target)} daily target
                    </p>
                </div>

                <div className="h-px bg-black/6 dark:bg-white/6" />

                <MetricBlock
                    label="Yesterday"
                    date={s.recent_date}
                    value={s.recent_spend}
                    target={s.daily_target}
                    hit={s.hit_recent}
                />
            </div>
        </div>
    );
}

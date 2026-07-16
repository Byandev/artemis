import { DeleteGoalDialog } from '@/components/ad-spend-goals/delete-goal-dialog';
import {
    BRAND_GRAD,
    Goal,
    GoalGraph,
    STATUS_META,
    TeamOption,
    WARN_GRAD,
    fmtDate,
} from '@/components/ad-spend-goals/goal-graph';
import {
    Goal as GoalFormShape,
    GoalFormDialog,
} from '@/components/ad-spend-goals/goal-form-dialog';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn, currencyFormatter } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    workspace: Workspace;
    goal: Goal;
    teams: TeamOption[];
    canManage: boolean;
}

function Fact({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <div className="mt-1 font-mono text-[14px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                {children}
            </div>
        </div>
    );
}

function PaceTile({
    label,
    value,
    sub,
    accent,
}: {
    label: string;
    value: React.ReactNode;
    sub?: React.ReactNode;
    accent?: string;
}) {
    return (
        <div className="rounded-[12px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <p className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <p
                className={cn(
                    'mt-1.5 flex items-center gap-1 font-mono text-[20px] leading-none font-semibold tracking-tight tabular-nums',
                    accent ?? 'text-gray-900 dark:text-gray-100',
                )}
            >
                {value}
            </p>
            {sub && (
                <p className="mt-1.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {sub}
                </p>
            )}
        </div>
    );
}

/** One member's row: their target slice, actual (yesterday), and progress. */
function MemberRow({
    name,
    target,
    recent,
    starting,
    reached,
    daysRemaining,
}: {
    name: string;
    target: number;
    recent: number;
    starting: number;
    reached: boolean;
    daysRemaining: number;
}) {
    const pct = target > 0 ? Math.min(100, (recent / target) * 100) : 0;
    const since = recent - starting;
    const toGo = Math.max(0, target - recent);
    const inc = daysRemaining > 0 && toGo > 0 ? toGo / daysRemaining : null;

    return (
        <div>
            <div className="flex items-center gap-3 sm:gap-4">
                <span
                    className={`h-2.5 w-2.5 shrink-0 rounded-full ${reached ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                />
                <span className="w-24 shrink-0 truncate text-[13px] font-medium text-gray-800 sm:w-40 dark:text-gray-100">
                    {name}
                </span>
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                    <div
                        className="h-full rounded-full"
                        style={{
                            width: `${reached ? 100 : pct}%`,
                            background: reached ? BRAND_GRAD : WARN_GRAD,
                        }}
                    />
                </div>
                <div className="w-32 shrink-0 text-right font-mono text-[11px] tabular-nums sm:w-44">
                    <span className="font-semibold text-gray-900 dark:text-gray-50">
                        {currencyFormatter(recent)}
                    </span>
                    <span className="text-gray-400 dark:text-gray-500">
                        {' '}
                        / {currencyFormatter(target)}
                    </span>
                    {reached ? (
                        <Check className="ml-1 inline h-3 w-3 text-brand-500" />
                    ) : (
                        <span className="ml-1 text-warning-600 dark:text-warning-400">
                            {Math.round(pct)}%
                        </span>
                    )}
                </div>
            </div>

            {/* progress since start · gap from yesterday to target */}
            <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 pl-[22px] font-mono text-[10px] text-gray-400 dark:text-gray-500">
                <span>start {currencyFormatter(starting)}</span>
                <span aria-hidden>·</span>
                <span
                    className={
                        since > 0
                            ? 'text-brand-600 dark:text-brand-400'
                            : since < 0
                              ? 'text-warning-600 dark:text-warning-400'
                              : ''
                    }
                >
                    {since > 0 ? '+' : since < 0 ? '−' : ''}
                    {currencyFormatter(Math.abs(since))} since start
                </span>
                {toGo > 0 && (
                    <>
                        <span aria-hidden>·</span>
                        <span className="text-warning-600 dark:text-warning-400">
                            {currencyFormatter(toGo)} to go
                        </span>
                        {inc !== null && (
                            <span className="text-brand-600 dark:text-brand-400">
                                · +{currencyFormatter(inc)}/day
                            </span>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

/** One rung of the milestone ladder: threshold, progress bar, reached / to-go. */
function MilestoneRung({
    amount,
    label,
    reached,
    reachedDate,
    yesterday,
    hasRecent,
    daysRemaining,
    isTarget,
}: {
    amount: number;
    label: string | null;
    reached: boolean;
    reachedDate: string | null;
    yesterday: number;
    hasRecent: boolean;
    daysRemaining: number;
    isTarget?: boolean;
}) {
    const toGo = Math.max(0, amount - yesterday);
    const inc = daysRemaining > 0 && toGo > 0 ? toGo / daysRemaining : null;
    const pct = amount > 0 ? Math.min(100, (yesterday / amount) * 100) : 0;

    return (
        <div className="flex items-center gap-3 sm:gap-4">
            <span
                className={`h-2.5 w-2.5 shrink-0 rounded-full ${reached ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600'}`}
            />
            <div className="w-24 shrink-0 sm:w-28">
                <p className="font-mono text-[13px] font-semibold text-gray-900 tabular-nums dark:text-gray-50">
                    {currencyFormatter(amount)}
                </p>
                <p className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    {isTarget ? 'Target' : label || 'Milestone'}
                </p>
            </div>
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                <div
                    className="h-full rounded-full"
                    style={{
                        width: `${reached ? 100 : pct}%`,
                        background: reached ? BRAND_GRAD : WARN_GRAD,
                    }}
                />
            </div>
            <div className="w-36 shrink-0 text-right sm:w-48">
                {reached ? (
                    <span className="inline-flex items-center gap-1 font-mono text-[11px] font-medium text-brand-600 dark:text-brand-400">
                        Reached
                        <Check className="h-3 w-3" />
                        {reachedDate && (
                            <span className="text-gray-400 dark:text-gray-500">
                                {' '}
                                · {fmtDate(reachedDate)}
                            </span>
                        )}
                    </span>
                ) : hasRecent ? (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        <span className="font-semibold text-warning-600 dark:text-warning-400">
                            {currencyFormatter(toGo)}
                        </span>{' '}
                        to go
                        {inc !== null && (
                            <span className="text-gray-400 dark:text-gray-500">
                                {' '}
                                · +{currencyFormatter(inc)}/day
                            </span>
                        )}
                    </span>
                ) : (
                    <span className="font-mono text-[11px] text-gray-300 dark:text-gray-600">
                        —
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * The ramp: ascending bars from today up to the last day, each day raised by a
 * fixed increment so the final day lands on the target. Warning (amber) while
 * below target, brand (teal) on the day it reaches it.
 */
function RampChart({
    yesterday,
    increment,
    target,
    days,
}: {
    yesterday: number;
    increment: number;
    target: number;
    days: number;
}) {
    const n = Math.min(days, 24);
    const bars = Array.from({ length: n }, (_, i) => {
        const projected = Math.min(target, yesterday + increment * (i + 1));
        return {
            projected,
            h: target > 0 ? (projected / target) * 100 : 0,
            isLast: i === n - 1 && days <= 24,
        };
    });

    return (
        <div className="mt-6">
            <div className="relative flex h-24 items-end gap-1">
                <div className="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-brand-500/40" />
                {bars.map((b, i) => (
                    <div
                        key={i}
                        className="relative h-full flex-1"
                        title={currencyFormatter(b.projected)}
                    >
                        <div
                            className="absolute bottom-0 w-full rounded-t-[3px]"
                            style={{
                                height: `${b.h}%`,
                                background: b.isLast
                                    ? 'linear-gradient(180deg,#10d3a1,#0eaa82)'
                                    : 'linear-gradient(180deg,#fdb022,#dc6803)',
                            }}
                        />
                    </div>
                ))}
            </div>
            <div className="mt-1.5 flex items-center justify-between font-mono text-[9px] tracking-wide text-gray-400 uppercase dark:text-gray-500">
                <span>
                    Today {currencyFormatter(Math.min(target, yesterday + increment))}
                </span>
                {days > 24 && (
                    <span className="text-gray-300 dark:text-gray-600">
                        first 24 of {days} days
                    </span>
                )}
                <span className="text-brand-600 dark:text-brand-400">
                    Last day → {currencyFormatter(target)}
                </span>
            </div>
        </div>
    );
}

export default function AdSpendGoalShow({
    workspace,
    goal,
    teams,
    canManage,
}: Props) {
    const canManageGoals =
        usePermission(PERMISSIONS.ManageAdSpendGoals) || canManage;

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const s = goal.status;
    const meta = STATUS_META[s.status] ?? STATUS_META.below;
    const indexUrl = `/workspaces/${workspace.slug}/ad-spend-goals`;

    // Daily-target pacing, computed here on the frontend.
    const target = goal.daily_target;
    const yesterday = s.recent_spend;
    const hasRecent = s.recent_date !== null;
    const short = hasRecent ? Math.max(0, target - yesterday) : null;
    const increment =
        short !== null && short > 0 && s.days_remaining > 0
            ? short / s.days_remaining
            : null;
    // Starting budget (spend on the start date, 0 if unrecorded) and how much
    // the team has grown since then.
    const startingSpend = s.starting_spend;
    const increaseSinceStart = hasRecent ? yesterday - startingSpend : null;

    const editShape: GoalFormShape = {
        id: goal.id,
        team_id: goal.team_id,
        daily_target: goal.daily_target,
        start_date: goal.start_date,
        end_date: goal.end_date,
        milestones: s.milestones.map((m) => ({
            amount: m.amount,
            label: m.label,
        })),
        members: s.members.map((m) => ({
            user_id: m.user_id,
            daily_target: m.daily_target,
        })),
    };

    return (
        <AppLayout>
            <Head title={`${goal.team_name ?? 'Goal'} - Ad Spend Goal`} />
            <div className="w-full px-4 py-4 md:px-6 md:py-6">
                <Link
                    href={indexUrl}
                    className="inline-flex items-center gap-1.5 font-mono text-[11px] font-medium text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Ad Spend Goals
                </Link>

                <div className="mt-4 flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="truncate text-[20px] font-semibold tracking-tight text-gray-900 dark:text-gray-50">
                                {goal.team_name ?? 'Unknown team'}
                            </h1>
                            <span
                                className={`rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${meta.className}`}
                            >
                                {meta.label}
                            </span>
                            {s.is_ended && (
                                <span className="rounded-full bg-stone-100 px-1.5 py-0.5 font-mono text-[9px] tracking-wide text-gray-400 uppercase dark:bg-zinc-800 dark:text-gray-500">
                                    Ended
                                </span>
                            )}
                        </div>
                        <p className="mt-1.5 font-mono text-[12px] text-gray-400 tabular-nums dark:text-gray-500">
                            {currencyFormatter(goal.daily_target)}/day ·{' '}
                            {fmtDate(goal.start_date)} – {fmtDate(goal.end_date)}
                        </p>
                    </div>

                    {canManageGoals && (
                        <div className="flex shrink-0 items-center gap-2">
                            <button
                                onClick={() => setEditOpen(true)}
                                className="flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 shadow-sm transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                <Pencil className="h-3.5 w-3.5" />
                                Edit
                            </button>
                            <button
                                onClick={() => setDeleteOpen(true)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg border border-black/8 bg-white text-gray-400 shadow-sm transition-all hover:bg-error-100 hover:text-error-600 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-500 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}
                </div>

                {/* Hero: gradient donut + headline numbers, with the goal facts. */}
                <div className="mt-6 rounded-[18px] border border-black/6 bg-gradient-to-br from-white to-stone-50/60 p-6 shadow-sm md:p-8 dark:border-white/6 dark:from-zinc-900 dark:to-zinc-950/40">
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Best day vs target
                    </p>
                    <div className="mt-8 grid gap-10 lg:grid-cols-[1.5fr_1fr] lg:gap-12">
                        <GoalGraph status={s} size={168} />

                        <div className="grid gap-5 border-t border-black/6 pt-6 sm:grid-cols-2 lg:grid-cols-1 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-12 dark:border-white/6">
                            <Fact label="Daily target">
                                {currencyFormatter(goal.daily_target)}
                            </Fact>
                            <Fact label="Period">
                                {fmtDate(goal.start_date)} –{' '}
                                {fmtDate(goal.end_date)}
                            </Fact>
                            <Fact label="Duration">{s.total_days} days</Fact>
                            <Fact label="Status">
                                <span
                                    className={`inline-block rounded-full px-2 py-0.5 text-[10px] font-medium ${meta.className}`}
                                >
                                    {meta.label}
                                </span>
                            </Fact>
                        </div>
                    </div>
                </div>

                {/* Daily-target pacing: how to ramp yesterday's spend up to target. */}
                <div className="mt-4 rounded-[18px] border border-black/6 bg-white p-6 shadow-sm md:p-8 dark:border-white/6 dark:bg-zinc-900">
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Hitting {currencyFormatter(target)}/day
                    </p>

                    {!hasRecent ? (
                        <p className="mt-4 font-mono text-[12px] text-gray-400 dark:text-gray-500">
                            No spend recorded yet — pacing appears once the team
                            has a day of ad spend.
                        </p>
                    ) : (
                        <>
                            {/* Where the team started vs. where it is now */}
                            <div className="mt-5 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-[12px] border border-black/6 bg-stone-50/60 px-4 py-3.5 dark:border-white/6 dark:bg-zinc-800/30">
                                <div>
                                    <p className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        Starting budget
                                    </p>
                                    <p className="mt-1 font-mono text-[18px] leading-none font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-50">
                                        {currencyFormatter(startingSpend)}
                                    </p>
                                    <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                        on {fmtDate(goal.start_date)}
                                    </p>
                                </div>
                                <ArrowRight className="h-4 w-4 text-gray-300 dark:text-gray-600" />
                                <div>
                                    <p className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        Yesterday
                                    </p>
                                    <p className="mt-1 font-mono text-[18px] leading-none font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-50">
                                        {currencyFormatter(yesterday)}
                                    </p>
                                    <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                        on {fmtDate(s.recent_date)}
                                    </p>
                                </div>
                                {increaseSinceStart !== null && (
                                    <div className="ml-auto text-right">
                                        <p className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Increase since start
                                        </p>
                                        <p
                                            className={cn(
                                                'mt-1 font-mono text-[18px] leading-none font-semibold tracking-tight tabular-nums',
                                                increaseSinceStart > 0
                                                    ? 'text-brand-600 dark:text-brand-400'
                                                    : increaseSinceStart < 0
                                                      ? 'text-warning-600 dark:text-warning-400'
                                                      : 'text-gray-500 dark:text-gray-400',
                                            )}
                                        >
                                            {increaseSinceStart > 0
                                                ? '+'
                                                : increaseSinceStart < 0
                                                  ? '−'
                                                  : ''}
                                            {currencyFormatter(
                                                Math.abs(increaseSinceStart),
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>

                            <p className="mt-5 text-[13px] leading-relaxed text-gray-600 dark:text-gray-300">
                                Yesterday hit{' '}
                                <span className="font-mono font-semibold text-gray-900 dark:text-gray-50">
                                    {currencyFormatter(yesterday)}
                                </span>
                                {short === 0 ? (
                                    <>
                                        {' '}
                                        — already at or above the{' '}
                                        {currencyFormatter(target)}/day target.{' '}
                                        <Check className="inline h-4 w-4 text-brand-500" />
                                    </>
                                ) : (
                                    <>
                                        ,{' '}
                                        <span className="font-mono font-semibold text-warning-600 dark:text-warning-400">
                                            {currencyFormatter(short!)} short
                                        </span>{' '}
                                        of {currencyFormatter(target)}.
                                        {increment !== null ? (
                                            <>
                                                {' '}
                                                With{' '}
                                                <span className="font-semibold">
                                                    {s.days_remaining} day
                                                    {s.days_remaining === 1
                                                        ? ''
                                                        : 's'}{' '}
                                                    left
                                                </span>
                                                , increase spend by about{' '}
                                                <span className="font-mono font-semibold text-brand-600 dark:text-brand-400">
                                                    +{currencyFormatter(increment)}
                                                    /day
                                                </span>{' '}
                                                to reach{' '}
                                                {currencyFormatter(target)} on the
                                                last day.
                                            </>
                                        ) : (
                                            <> The goal period has ended.</>
                                        )}
                                    </>
                                )}
                            </p>

                            {increment !== null && (
                                <RampChart
                                    yesterday={yesterday}
                                    increment={increment}
                                    target={target}
                                    days={s.days_remaining}
                                />
                            )}

                            <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
                                <PaceTile
                                    label="Short of target"
                                    value={
                                        short === 0 ? (
                                            <>
                                                Hit
                                                <Check className="h-4 w-4 text-brand-500" />
                                            </>
                                        ) : (
                                            `+${currencyFormatter(short!)}`
                                        )
                                    }
                                    accent={
                                        short === 0
                                            ? 'text-brand-600 dark:text-brand-400'
                                            : 'text-warning-600 dark:text-warning-400'
                                    }
                                    sub="vs yesterday"
                                />
                                <PaceTile
                                    label="Increase / day"
                                    value={
                                        increment !== null
                                            ? `+${currencyFormatter(increment)}`
                                            : '—'
                                    }
                                    accent={
                                        increment !== null
                                            ? 'text-brand-600 dark:text-brand-400'
                                            : undefined
                                    }
                                    sub={
                                        increment !== null
                                            ? `ramp to ${currencyFormatter(target)} by the last day`
                                            : 'period ended'
                                    }
                                />
                                <PaceTile
                                    label="Days remaining"
                                    value={s.days_remaining}
                                    sub={`of ${s.total_days} days`}
                                />
                            </div>
                        </>
                    )}
                </div>

                {/* Per-member breakdown (only when the goal splits by member) */}
                {s.members.length > 0 && (
                    <div className="mt-4 rounded-[18px] border border-black/6 bg-white p-6 shadow-sm md:p-8 dark:border-white/6 dark:bg-zinc-900">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Members
                            </p>
                            {(() => {
                                const memberRecent = s.members.reduce(
                                    (sum, m) => sum + m.recent_spend,
                                    0,
                                );
                                const meets = memberRecent + 0.01 >= target;
                                return (
                                    <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                        Team{' '}
                                        <span
                                            className={
                                                meets
                                                    ? 'font-semibold text-brand-600 dark:text-brand-400'
                                                    : 'font-semibold text-warning-600 dark:text-warning-400'
                                            }
                                        >
                                            {currencyFormatter(memberRecent)}
                                        </span>{' '}
                                        / {currencyFormatter(target)} target
                                    </p>
                                );
                            })()}
                        </div>
                        <div className="mt-5 space-y-4">
                            {s.members.map((m) => (
                                <MemberRow
                                    key={m.user_id}
                                    name={m.name ?? 'Unknown'}
                                    target={m.daily_target}
                                    recent={m.recent_spend}
                                    starting={m.starting_spend}
                                    reached={m.reached}
                                    daysRemaining={s.days_remaining}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* Milestones ladder (only when the goal has milestones) */}
                {s.milestones.length > 0 && (
                    <div className="mt-4 rounded-[18px] border border-black/6 bg-white p-6 shadow-sm md:p-8 dark:border-white/6 dark:bg-zinc-900">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Milestones
                        </p>
                        <div className="mt-5 space-y-4">
                            {s.milestones.map((m) => (
                                <MilestoneRung
                                    key={m.id}
                                    amount={m.amount}
                                    label={m.label}
                                    reached={m.reached}
                                    reachedDate={m.reached_date}
                                    yesterday={yesterday}
                                    hasRecent={hasRecent}
                                    daysRemaining={s.days_remaining}
                                />
                            ))}
                            {/* Final rung: the goal's own target. */}
                            <MilestoneRung
                                amount={target}
                                label={null}
                                reached={s.hit_ever}
                                reachedDate={s.peak_date}
                                yesterday={yesterday}
                                hasRecent={hasRecent}
                                daysRemaining={s.days_remaining}
                                isTarget
                            />
                        </div>
                    </div>
                )}
            </div>

            {canManageGoals && (
                <GoalFormDialog
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    goal={editShape}
                    workspace={workspace}
                    teams={teams}
                />
            )}

            {canManageGoals && (
                <DeleteGoalDialog
                    goal={deleteOpen ? goal : null}
                    workspace={workspace}
                    onClose={() => setDeleteOpen(false)}
                />
            )}
        </AppLayout>
    );
}

import {
    DashboardTab,
    DashboardTabNav,
} from '@/components/sales-marketing/dashboard-tabs';
import { DeleteSalesTargetDialog } from '@/components/sales-targets/delete-sales-target-dialog';
import {
    SalesTargetFormDialog,
    TeamOption,
} from '@/components/sales-targets/sales-target-form-dialog';
import AppLayout from '@/layouts/app-layout';
import { currencyFormatter } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MonitorPlay, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    SalesTarget,
    budgetOf,
    dayOffset,
    formatTargetDate,
    statusFor,
    totalOf,
} from './shared';

interface Props {
    workspace: Workspace;
    target: SalesTarget;
    teams: TeamOption[];
    canManage: boolean;
    tabs?: DashboardTab[];
    activeTab?: string;
}

export default function SalesTargetShow({
    workspace,
    target,
    teams,
    canManage,
    tabs,
    activeTab,
}: Props) {
    const hasTabs = !!tabs?.length;
    const base = `/workspaces/${workspace.slug}/sales-marketing/dashboard/sales-targets`;

    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const status = statusFor(dayOffset(target.date));
    const total = totalOf(target);
    const budget = budgetOf(target);

    return (
        <AppLayout>
            <Head title={`${target.name} - Sales Target`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {hasTabs && <DashboardTabNav tabs={tabs!} active={activeTab} />}

                <Link
                    href={base}
                    className="mt-4 inline-flex items-center gap-1.5 font-mono text-[11px] font-medium text-gray-400 transition-colors hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Sales Targets
                </Link>

                <div className="mt-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                                {target.name}
                            </h1>
                            <span
                                className={`rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${status.pill}`}
                            >
                                {status.label}
                            </span>
                        </div>
                        <p className="mt-1 font-mono text-[12px] text-gray-400 dark:text-gray-500">
                            {formatTargetDate(target.date)}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* The board scored on *this* target rather than today's.
                            Password-gated and built for a wall display, so it
                            opens in its own tab like the index's link does. */}
                        <a
                            href={`/public/workspaces/${workspace.slug}/sales-targets/${target.id}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            <MonitorPlay className="h-3.5 w-3.5" />
                            Public Gameboard
                        </a>

                        {canManage && (
                            <>
                                <button
                                    onClick={() => setEditing(true)}
                                    className="flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    Edit
                                </button>
                                <button
                                    onClick={() => setDeleting(true)}
                                    className="flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    Delete
                                </button>
                            </>
                        )}
                    </div>
                </div>

                <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-[16px] border border-black/6 bg-white px-5 py-4 dark:border-white/6 dark:bg-zinc-900">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Target Sale
                        </p>
                        <p className="mt-1 font-mono text-[20px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {currencyFormatter(total)}
                        </p>
                    </div>
                    <div className="rounded-[16px] border border-black/6 bg-white px-5 py-4 dark:border-white/6 dark:bg-zinc-900">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Ad Budget
                        </p>
                        <p className="mt-1 font-mono text-[20px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {budget > 0 ? currencyFormatter(budget) : '—'}
                        </p>
                    </div>
                    <div className="rounded-[16px] border border-black/6 bg-white px-5 py-4 dark:border-white/6 dark:bg-zinc-900">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Target ROAS
                        </p>
                        <p className="mt-1 font-mono text-[20px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {target.target_roas
                                ? Number(target.target_roas).toFixed(2)
                                : '—'}
                        </p>
                    </div>
                    <div className="rounded-[16px] border border-black/6 bg-white px-5 py-4 dark:border-white/6 dark:bg-zinc-900">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Teams
                        </p>
                        <p className="mt-1 font-mono text-[20px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {target.team_targets.length}
                        </p>
                    </div>
                </div>

                <div className="mt-4 overflow-hidden rounded-[16px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="flex items-center gap-3 border-b border-black/6 px-5 py-2.5 dark:border-white/6">
                        <span className="flex-1 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Team
                        </span>
                        <span className="w-36 text-right font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Target
                        </span>
                        <span className="w-36 text-right font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Ad Budget
                        </span>
                    </div>

                    <div className="divide-y divide-black/4 dark:divide-white/4">
                        {target.team_targets.map((row) => {
                            const amount = Number(row.sales_target ?? 0);
                            const share =
                                total > 0
                                    ? Math.round((amount / total) * 100)
                                    : 0;

                            return (
                                <div
                                    key={row.id}
                                    className="flex items-center gap-3 px-5 py-3"
                                >
                                    <span className="flex-1 truncate text-[12px] text-gray-700 dark:text-gray-300">
                                        {row.team?.name ?? 'Unknown team'}
                                    </span>
                                    <div className="w-36 text-right">
                                        <span className="font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-300">
                                            {row.sales_target === null
                                                ? '—'
                                                : currencyFormatter(amount)}
                                        </span>
                                        {row.sales_target !== null && (
                                            <span className="ml-1.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                {share}%
                                            </span>
                                        )}
                                    </div>
                                    <span className="w-36 text-right font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-300">
                                        {row.ad_budget === null
                                            ? '—'
                                            : currencyFormatter(
                                                  Number(row.ad_budget),
                                              )}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <SalesTargetFormDialog
                workspace={workspace}
                teams={teams}
                open={editing}
                onOpenChange={setEditing}
                target={target}
            />

            <DeleteSalesTargetDialog
                workspace={workspace}
                target={
                    deleting
                        ? {
                              id: target.id,
                              name: target.name,
                              date: target.date,
                              team_count: target.team_targets.length,
                          }
                        : null
                }
                onClose={() => setDeleting(false)}
            />
        </AppLayout>
    );
}

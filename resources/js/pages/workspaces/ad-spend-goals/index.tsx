import { DeleteGoalDialog } from '@/components/ad-spend-goals/delete-goal-dialog';
import {
    BRAND_GRAD,
    Goal,
    STATUS_META,
    TeamOption,
    WARN_GRAD,
    bestOf,
    fmtDate,
} from '@/components/ad-spend-goals/goal-graph';
import {
    Goal as GoalFormShape,
    GoalFormDialog,
} from '@/components/ad-spend-goals/goal-form-dialog';
import PageHeader from '@/components/common/PageHeader';
import { DataTable } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { currencyFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    Check,
    ChevronRight,
    MoreHorizontal,
    Pencil,
    Target,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface Props {
    workspace: Workspace;
    goals: PaginatedData<Goal>;
    teams: TeamOption[];
    canManage: boolean;
}

/** Small colored dot keyed to a goal's status. */
const STATUS_DOT: Record<string, string> = {
    on_target: 'bg-brand-500',
    slipping: 'bg-warning-500',
    below: 'bg-error-500',
    upcoming: 'bg-gray-300 dark:bg-gray-600',
};

function BestIndicator({ goal }: { goal: Goal }) {
    const b = bestOf(goal.status);
    if (!b.hasData) {
        return (
            <span className="font-mono text-[11px] text-gray-300 dark:text-gray-600">
                no data
            </span>
        );
    }
    const arc = Math.max(0, Math.min(100, b.pct));

    return (
        <div className="flex items-center gap-2.5">
            <div className="h-1.5 w-full max-w-[120px] overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                <div
                    className="h-full rounded-full"
                    style={{
                        width: `${arc}%`,
                        background: b.hit ? BRAND_GRAD : WARN_GRAD,
                    }}
                />
            </div>
            <span
                className={`flex shrink-0 items-center gap-0.5 font-mono text-[11px] font-semibold tabular-nums ${b.hit ? 'text-brand-600 dark:text-brand-400' : 'text-warning-600 dark:text-warning-400'}`}
            >
                {Math.round(b.pct)}%
                {b.hit && <Check className="h-3 w-3" />}
            </span>
        </div>
    );
}

export default function AdSpendGoalsIndex({
    workspace,
    goals,
    teams,
    canManage,
}: Props) {
    const canManageGoals =
        usePermission(PERMISSIONS.ManageAdSpendGoals) || canManage;

    const [createOpen, setCreateOpen] = useState(false);
    const [editingGoal, setEditingGoal] = useState<Goal | null>(null);
    const [goalToDelete, setGoalToDelete] = useState<Goal | null>(null);

    const editShape: GoalFormShape | null = editingGoal
        ? {
              id: editingGoal.id,
              team_id: editingGoal.team_id,
              daily_target: editingGoal.daily_target,
              start_date: editingGoal.start_date,
              end_date: editingGoal.end_date,
              milestones: editingGoal.status.milestones.map((m) => ({
                  amount: m.amount,
                  label: m.label,
              })),
          }
        : null;

    const showUrl = (goal: Goal) =>
        `/workspaces/${workspace.slug}/ad-spend-goals/${goal.id}`;

    const columns: ColumnDef<Goal>[] = [
        {
            accessorKey: 'team_name',
            header: 'Team',
            meta: { cellClassName: 'align-middle' },
            cell: ({ row }) => {
                const goal = row.original;
                return (
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span
                            className={`h-2 w-2 shrink-0 rounded-full ${STATUS_DOT[goal.status.status] ?? STATUS_DOT.below}`}
                        />
                        <span className="truncate text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                            {goal.team_name ?? 'Unknown team'}
                        </span>
                        {goal.status.is_ended && (
                            <span className="rounded-full bg-stone-100 px-1.5 py-0.5 font-mono text-[9px] tracking-wide text-gray-400 uppercase dark:bg-zinc-800 dark:text-gray-500">
                                Ended
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'daily_target',
            header: 'Daily Target',
            meta: { cellClassName: 'align-middle whitespace-nowrap' },
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                    {currencyFormatter(row.original.daily_target)}
                    <span className="font-normal text-gray-400 dark:text-gray-500">
                        /day
                    </span>
                </span>
            ),
        },
        {
            id: 'period',
            header: 'Period',
            meta: { cellClassName: 'align-middle whitespace-nowrap' },
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 tabular-nums dark:text-gray-400">
                    {fmtDate(row.original.start_date)} –{' '}
                    {fmtDate(row.original.end_date)}
                </span>
            ),
        },
        {
            id: 'best',
            header: 'Best vs Target',
            meta: { cellClassName: 'align-middle min-w-[190px]' },
            cell: ({ row }) => <BestIndicator goal={row.original} />,
        },
        {
            id: 'status',
            header: 'Status',
            meta: { cellClassName: 'align-middle' },
            cell: ({ row }) => {
                const meta =
                    STATUS_META[row.original.status.status] ??
                    STATUS_META.below;
                return (
                    <span
                        className={`inline-block rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${meta.className}`}
                    >
                        {meta.label}
                    </span>
                );
            },
        },
        {
            id: 'actions',
            header: '',
            meta: {
                headerClassName: 'w-10',
                cellClassName: 'align-middle w-10',
            },
            cell: ({ row }) => {
                const goal = row.original;
                if (!canManageGoals) {
                    return (
                        <div className="flex justify-end">
                            <ChevronRight className="h-4 w-4 text-gray-300 dark:text-gray-600" />
                        </div>
                    );
                }
                return (
                    <div
                        className="flex justify-end"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg text-gray-300 transition-all hover:bg-stone-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-4 w-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-32">
                                <DropdownMenuItem asChild>
                                    <Link href={showUrl(goal)}>
                                        <ChevronRight />
                                        View
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setEditingGoal(goal)}
                                >
                                    <Pencil />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setGoalToDelete(goal)}
                                >
                                    <Trash2 />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Ad Spend Goals`} />
            <div className="w-full px-4 py-4 md:px-6 md:py-6">
                <PageHeader
                    title="Ad Spend Goals"
                    description="Set a daily ad-spend target per team and track how they're hitting it"
                >
                    {canManageGoals && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white shadow-sm transition-all hover:bg-brand-700"
                        >
                            <Target className="h-3.5 w-3.5" />
                            New Goal
                        </button>
                    )}
                </PageHeader>

                {goals.total === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-[16px] border border-dashed border-black/8 bg-white py-20 dark:border-white/8 dark:bg-zinc-900">
                        <div className="rounded-2xl bg-stone-100 p-3.5 dark:bg-zinc-800">
                            <Target className="h-7 w-7 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p className="mt-4 text-[14px] font-semibold text-gray-700 dark:text-gray-200">
                            No ad spend goals yet
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            Create a goal to track a team's daily ad spend.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-[16px] border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={goals.data}
                            meta={omit(goals, ['data'])}
                            onRowClick={(goal) => router.visit(showUrl(goal))}
                            onFetch={(params) =>
                                router.get(
                                    `/workspaces/${workspace.slug}/ad-spend-goals`,
                                    {
                                        page: params?.page ?? 1,
                                        per_page: params?.per_page ?? undefined,
                                    },
                                    {
                                        preserveState: true,
                                        replace: true,
                                        preserveScroll: true,
                                        only: ['goals'],
                                    },
                                )
                            }
                        />
                    </div>
                )}

                {canManageGoals && (
                    <GoalFormDialog
                        open={createOpen || editingGoal !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setCreateOpen(false);
                                setEditingGoal(null);
                            }
                        }}
                        goal={editShape}
                        workspace={workspace}
                        teams={teams}
                    />
                )}

                {canManageGoals && (
                    <DeleteGoalDialog
                        goal={goalToDelete}
                        workspace={workspace}
                        onClose={() => setGoalToDelete(null)}
                    />
                )}
            </div>
        </AppLayout>
    );
}

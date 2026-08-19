import {
    DashboardTab,
    DashboardTabNav,
} from '@/components/sales-marketing/dashboard-tabs';
import { DeleteSalesTargetDialog } from '@/components/sales-targets/delete-sales-target-dialog';
import {
    SalesTargetFormDialog,
    TeamOption,
} from '@/components/sales-targets/sales-target-form-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { currencyFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { Pencil, Plus, Target, Trash2 } from 'lucide-react';
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
    targets: PaginatedData<SalesTarget>;
    teams: TeamOption[];
    canManage: boolean;
    tabs?: DashboardTab[];
    activeTab?: string;
}

export default function SalesTargetsIndex({
    workspace,
    targets,
    teams,
    canManage,
    tabs,
    activeTab,
}: Props) {
    const hasTabs = !!tabs?.length;
    const base = `/workspaces/${workspace.slug}/sales-marketing/dashboard/sales-targets`;

    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<SalesTarget | null>(null);
    const [deleting, setDeleting] = useState<SalesTarget | null>(null);

    const columns: ColumnDef<SalesTarget>[] = [
        {
            accessorKey: 'name',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader enabled={false} column={column} title="Name" />
            ),
            cell: ({ row }) => {
                const status = statusFor(dayOffset(row.original.date));

                return (
                    <div className="flex items-center gap-2">
                        <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {row.original.name}
                        </span>
                        <span
                            className={`shrink-0 rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${status.pill}`}
                        >
                            {status.label}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'date',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader enabled={false} column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {formatTargetDate(row.original.date)}
                </span>
            ),
        },
        {
            id: 'target_sale',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    enabled={false}
                    column={column}
                    title="Target Sale"
                />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-medium text-gray-800 tabular-nums dark:text-gray-200">
                    {currencyFormatter(totalOf(row.original))}
                </span>
            ),
        },
        {
            id: 'ad_budget',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    enabled={false}
                    column={column}
                    title="Ad Budget"
                />
            ),
            cell: ({ row }) => {
                const budget = budgetOf(row.original);

                return (
                    <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                        {budget > 0 ? currencyFormatter(budget) : '—'}
                    </span>
                );
            },
        },
        {
            id: 'target_roas',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    enabled={false}
                    column={column}
                    title="Target ROAS"
                />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                    {row.original.target_roas
                        ? Number(row.original.target_roas).toFixed(2)
                        : '—'}
                </span>
            ),
        },
        ...(canManage
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => (
                          <div
                              className="flex justify-end gap-1"
                              // The row navigates to the detail page; the buttons
                              // inside it must not trigger that too.
                              onClick={(e) => e.stopPropagation()}
                          >
                              <button
                                  onClick={() => setEditing(row.original)}
                                  aria-label={`Edit ${row.original.name}`}
                                  title="Edit"
                                  className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:text-gray-700 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:text-gray-200"
                              >
                                  <Pencil className="h-3.5 w-3.5" />
                              </button>
                              <button
                                  onClick={() => setDeleting(row.original)}
                                  aria-label={`Delete ${row.original.name}`}
                                  title="Delete"
                                  className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-500 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                              >
                                  <Trash2 className="h-3.5 w-3.5" />
                              </button>
                          </div>
                      ),
                  } as ColumnDef<SalesTarget>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Sales Targets`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {hasTabs && <DashboardTabNav tabs={tabs!} active={activeTab} />}

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Sales Targets
                        </h1>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            A dated target and the sale it's aiming for. Open
                            one to see the per-team amounts.
                        </p>
                    </div>
                    {/* The public board lives on a target's own detail page — it is
                        always scored on one specific target, so there is nothing
                        for it to point at from the list. */}
                    <div className="flex items-center gap-2">
                        {canManage && teams.length > 0 && (
                            <button
                                onClick={() => setCreateOpen(true)}
                                className="flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                New Sales Target
                            </button>
                        )}
                    </div>
                </div>

                {targets.total === 0 ? (
                    <div className="mt-4 flex flex-col items-center justify-center rounded-[16px] border border-dashed border-black/8 bg-white py-20 dark:border-white/8 dark:bg-zinc-900">
                        <div className="rounded-2xl bg-stone-100 p-3.5 dark:bg-zinc-800">
                            <Target className="h-7 w-7 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p className="mt-4 text-[14px] font-semibold text-gray-700 dark:text-gray-200">
                            No sales targets yet
                        </p>
                        {/* Empty state names the blocker, or offers the next step. */}
                        {teams.length === 0 ? (
                            <p className="mt-1 max-w-xs text-center text-[12px] text-gray-400 dark:text-gray-500">
                                Create a team first — a sales target sets an
                                amount per team.
                            </p>
                        ) : (
                            <>
                                <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                                    Set what each team should hit on a given
                                    day.
                                </p>
                                {canManage && (
                                    <button
                                        onClick={() => setCreateOpen(true)}
                                        className="mt-4 flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                        New Sales Target
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                ) : (
                    <div className="mt-4 overflow-hidden rounded-[16px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={targets.data}
                            meta={omit(targets, ['data'])}
                            onRowClick={(target) =>
                                router.visit(`${base}/${target.id}`)
                            }
                            onFetch={(params) =>
                                router.get(
                                    base,
                                    {
                                        page: params?.page ?? 1,
                                        per_page: params?.per_page ?? undefined,
                                    },
                                    {
                                        preserveState: true,
                                        replace: true,
                                        preserveScroll: true,
                                        only: ['targets'],
                                    },
                                )
                            }
                        />
                    </div>
                )}
            </div>

            <SalesTargetFormDialog
                workspace={workspace}
                teams={teams}
                open={createOpen}
                onOpenChange={setCreateOpen}
            />

            <SalesTargetFormDialog
                workspace={workspace}
                teams={teams}
                open={!!editing}
                onOpenChange={(open) => !open && setEditing(null)}
                target={editing}
            />

            <DeleteSalesTargetDialog
                workspace={workspace}
                target={
                    deleting && {
                        id: deleting.id,
                        name: deleting.name,
                        date: deleting.date,
                        team_count: deleting.team_targets.length,
                    }
                }
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}

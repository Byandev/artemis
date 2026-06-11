import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { ArrowRight, Check, CheckCircle2, ChevronDown, X } from 'lucide-react';
import { useState } from 'react';
import {
    actionBadgeClass,
    type AdAccountOption,
    executionModeLabel,
    metricLabel,
    type OptimizationProposal,
    optimizationRulesUrl,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    proposals: PaginatedData<OptimizationProposal>;
    adAccounts: AdAccountOption[];
    actions: string[];
    budgetImpact: number;
    query?: {
        page?: number | string;
        perPage?: number | string;
        accountIds?: string[];
        actions?: string[];
    };
}

function MultiFilter({
    label,
    options,
    selected,
    onChange,
}: {
    label: string;
    options: { value: string; label: string }[];
    selected: string[];
    onChange: (next: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const toggle = (v: string) => {
        const set = new Set(selected);
        if (set.has(v)) set.delete(v);
        else set.add(v);
        onChange(options.filter((o) => set.has(o.value)).map((o) => o.value));
    };
    const text =
        selected.length === 0
            ? `All ${label.toLowerCase()}`
            : `${selected.length} ${label.toLowerCase()}`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="h-9 gap-1.5">
                    {text}
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-56 p-1">
                {options.length === 0 && (
                    <p className="px-2 py-2 text-[11px] text-gray-400">
                        No options
                    </p>
                )}
                {options.map((o) => {
                    const checked = selected.includes(o.value);
                    return (
                        <button
                            key={o.value}
                            type="button"
                            onClick={() => toggle(o.value)}
                            className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-[12px] text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            <span className="truncate">{o.label}</span>
                            {checked && (
                                <Check className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                            )}
                        </button>
                    );
                })}
                {selected.length > 0 && (
                    <button
                        type="button"
                        onClick={() => onChange([])}
                        className="mt-1 w-full border-t border-black/6 px-2 pt-1.5 text-left text-[11px] text-gray-400 hover:text-gray-600 dark:border-white/6"
                    >
                        Clear
                    </button>
                )}
            </PopoverContent>
        </Popover>
    );
}

const fmt = (v: string | null) =>
    v === null
        ? null
        : Number(v).toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });

const isBudget = (action: string) =>
    action === 'increase_budget' || action === 'decrease_budget';

/** Net budget effect of a single proposal (matches the server's budgetImpact). */
const proposalImpact = (p: OptimizationProposal): number => {
    if (p.action === 'pause') return -(Number(p.target_budget) || 0);
    if (p.action === 'enable') return Number(p.target_budget) || 0;
    if (isBudget(p.action) && p.current_value !== null && p.new_value !== null) {
        return Number(p.new_value) - Number(p.current_value);
    }
    return 0;
};

const checkboxClass =
    'h-3.5 w-3.5 cursor-pointer rounded border-gray-300 accent-emerald-500 dark:border-zinc-600';

export default function OptimizationApprovals({
    workspace,
    proposals,
    adAccounts,
    actions,
    budgetImpact,
    query,
}: Props) {
    const indexUrl = optimizationRulesUrl(workspace.slug);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [bulkBusy, setBulkBusy] = useState(false);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [accountIds, setAccountIds] = useState<string[]>(
        query?.accountIds ?? [],
    );
    const [actionFilters, setActionFilters] = useState<string[]>(
        query?.actions ?? [],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
        { title: 'Approvals', href: `${indexUrl}/approvals` },
    ];

    const navigate = (overrides: Record<string, unknown> = {}) => {
        // Selection is per-page; drop it whenever the visible set changes.
        setSelected(new Set());
        router.get(
            `${indexUrl}/approvals`,
            {
                page: proposals.current_page,
                per_page: query?.perPage ?? proposals.per_page,
                ad_account_id: accountIds.length ? accountIds : undefined,
                action: actionFilters.length ? actionFilters : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: [
                    'proposals',
                    'adAccounts',
                    'actions',
                    'budgetImpact',
                    'query',
                ],
            },
        );
    };

    const rows = proposals.data ?? [];
    const pageIds = rows.map((p) => p.id);
    const allChecked =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const someChecked = pageIds.some((id) => selected.has(id));

    const toggle = (id: number) =>
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    const toggleAll = () =>
        setSelected(
            allChecked ? new Set() : new Set([...selected, ...pageIds]),
        );

    const selectedRows = rows.filter((p) => selected.has(p.id));
    const selectedImpact = selectedRows.reduce(
        (sum, p) => sum + proposalImpact(p),
        0,
    );

    const bulkReview = (decision: 'bulk-approve' | 'bulk-reject') => {
        if (selected.size === 0) return;
        setBulkBusy(true);
        router.post(
            `${indexUrl}/approvals/${decision}`,
            { ids: Array.from(selected) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBulkBusy(false);
                    setSelected(new Set());
                },
            },
        );
    };

    const onAccounts = (next: string[]) => {
        setAccountIds(next);
        navigate({ page: 1, ad_account_id: next.length ? next : undefined });
    };
    const onActions = (next: string[]) => {
        setActionFilters(next);
        navigate({ page: 1, action: next.length ? next : undefined });
    };

    const review = (
        proposal: OptimizationProposal,
        decision: 'approve' | 'reject',
    ) => {
        setBusyId(proposal.id);
        router.post(
            `${indexUrl}/approvals/${proposal.id}/${decision}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    };

    const columns: ColumnDef<OptimizationProposal>[] = [
        {
            id: 'select',
            header: () => (
                <input
                    type="checkbox"
                    aria-label="Select all"
                    checked={allChecked}
                    ref={(el) => {
                        if (el) el.indeterminate = !allChecked && someChecked;
                    }}
                    onChange={toggleAll}
                    className={checkboxClass}
                />
            ),
            cell: ({ row }) => (
                <input
                    type="checkbox"
                    aria-label="Select proposal"
                    checked={selected.has(row.original.id)}
                    onChange={() => toggle(row.original.id)}
                    onClick={(e) => e.stopPropagation()}
                    className={checkboxClass}
                />
            ),
        },
        {
            id: 'target',
            header: 'Target',
            cell: ({ row }) => {
                const p = row.original;
                return (
                    <div className="min-w-0">
                        <div className="truncate font-medium text-gray-800 dark:text-gray-100">
                            {p.target_name ??
                                `${titleCase(p.target_type)} ${p.target_id}`}
                        </div>
                        <div className="text-[11px] text-gray-400">
                            {titleCase(p.target_type)}
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'rule',
            header: 'Rule',
            cell: ({ row }) => {
                const p = row.original;
                return (
                    <div className="flex flex-col items-start gap-1">
                        <span className="text-gray-700 dark:text-gray-200">
                            {p.rule?.name ?? 'Rule'}
                        </span>
                        {p.rule && (
                            <Badge
                                variant="outline"
                                className="text-[10px] font-medium"
                            >
                                {executionModeLabel(p.rule.execution_mode)}
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'account',
            header: 'Ad account',
            cell: ({ row }) => (
                <span className="text-gray-500 dark:text-gray-400">
                    {row.original.ad_account?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'action',
            header: 'Action',
            cell: ({ row }) => {
                const p = row.original;
                return (
                    <div className="flex flex-col items-start gap-1">
                        <Badge
                            variant="outline"
                            className={actionBadgeClass(p.action)}
                        >
                            {titleCase(p.action)}
                        </Badge>
                        {isBudget(p.action) &&
                            fmt(p.current_value) !== null &&
                            fmt(p.new_value) !== null && (
                                <span className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-600 dark:text-gray-300">
                                    {fmt(p.current_value)}
                                    <ArrowRight className="h-3 w-3 text-gray-400" />
                                    {fmt(p.new_value)}
                                </span>
                            )}
                        {p.target_budget != null &&
                            Number(p.target_budget) > 0 &&
                            (p.action === 'pause' || p.action === 'enable') && (
                                <span
                                    className={
                                        p.action === 'pause'
                                            ? 'text-[11px] font-semibold text-red-600 dark:text-red-400'
                                            : 'text-[11px] font-semibold text-emerald-600 dark:text-emerald-400'
                                    }
                                >
                                    {p.action === 'pause' ? '−' : '+'}
                                    {fmt(String(p.target_budget))}
                                </span>
                            )}
                    </div>
                );
            },
        },
        {
            id: 'conditions',
            header: 'Why',
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    {row.original.conditions_snapshot.map((c, i) => (
                        <span
                            key={i}
                            className="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400"
                        >
                            {c.passed ? (
                                <Check className="h-3 w-3 shrink-0 text-emerald-500" />
                            ) : (
                                <X className="h-3 w-3 shrink-0 text-gray-400" />
                            )}
                            <span className="font-medium text-gray-700 dark:text-gray-200">
                                {metricLabel(c.metric)}
                            </span>
                            {c.operator} {c.threshold}
                            <span className="text-gray-400">
                                (
                                {c.actual_value === null
                                    ? 'n/a'
                                    : Number(c.actual_value).toLocaleString()}
                                )
                            </span>
                            {c.metric !== 'budget' &&
                                c.metric !== 'running_days' &&
                                c.metric !== 'last_modified_in_hours' && (
                                    <span className="text-gray-400">
                                        · {titleCase(c.time_window)}
                                    </span>
                                )}
                        </span>
                    ))}
                </div>
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={busyId === row.original.id}
                        onClick={() => review(row.original, 'reject')}
                    >
                        <X className="mr-1 h-4 w-4" />
                        Reject
                    </Button>
                    <Button
                        size="sm"
                        disabled={busyId === row.original.id}
                        onClick={() => review(row.original, 'approve')}
                    >
                        <Check className="mr-1 h-4 w-4" />
                        Approve
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Optimization Approvals" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Optimization Approvals"
                    description="Review the changes optimization rules want to make before they run."
                >
                    <MultiFilter
                        label="ad accounts"
                        options={adAccounts.map((a) => ({
                            value: a.id,
                            label: a.name,
                        }))}
                        selected={accountIds}
                        onChange={onAccounts}
                    />
                    <MultiFilter
                        label="actions"
                        options={actions.map((a) => ({
                            value: a,
                            label: titleCase(a),
                        }))}
                        selected={actionFilters}
                        onChange={onActions}
                    />
                </PageHeader>

                {proposals.total === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="h-6 w-6" />
                        </div>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Nothing to review
                        </p>
                        <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                            Pending optimization changes will appear here after
                            the daily evaluation runs.
                        </p>
                    </div>
                ) : (
                    <>
                        {(() => {
                            const hasSelection = selected.size > 0;
                            const impact = hasSelection
                                ? selectedImpact
                                : budgetImpact;
                            return (
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-[12px] border border-black/6 bg-white px-4 py-2.5 dark:border-white/6 dark:bg-zinc-900">
                                    <div className="flex items-center gap-2">
                                        <span className="text-[12px] text-gray-500 dark:text-gray-400">
                                            {hasSelection
                                                ? `Net budget impact of ${selected.size} selected`
                                                : `Net budget impact if all ${
                                                      accountIds.length > 0 ||
                                                      actionFilters.length > 0
                                                          ? 'shown '
                                                          : ''
                                                  }proposals are approved`}
                                        </span>
                                        <span
                                            className={
                                                impact > 0
                                                    ? 'text-[15px] font-semibold text-emerald-600 dark:text-emerald-400'
                                                    : impact < 0
                                                      ? 'text-[15px] font-semibold text-red-600 dark:text-red-400'
                                                      : 'text-[15px] font-semibold text-gray-600 dark:text-gray-300'
                                            }
                                        >
                                            {impact > 0
                                                ? '+'
                                                : impact < 0
                                                  ? '−'
                                                  : ''}
                                            {fmt(String(Math.abs(impact)))}
                                        </span>
                                    </div>

                                    {hasSelection && (
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={bulkBusy}
                                                onClick={() =>
                                                    bulkReview('bulk-reject')
                                                }
                                            >
                                                <X className="mr-1 h-4 w-4" />
                                                Reject {selected.size}
                                            </Button>
                                            <Button
                                                size="sm"
                                                disabled={bulkBusy}
                                                onClick={() =>
                                                    bulkReview('bulk-approve')
                                                }
                                            >
                                                <Check className="mr-1 h-4 w-4" />
                                                Approve {selected.size}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            );
                        })()}
                        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <DataTable
                                columns={columns as ColumnDef<unknown>[]}
                                data={(proposals.data ?? []) as unknown[]}
                                meta={omit(proposals, ['data'])}
                                onFetch={(params) =>
                                    navigate({
                                        page: params?.page ?? 1,
                                        per_page:
                                            params?.per_page ??
                                            query?.perPage ??
                                            proposals.per_page,
                                    })
                                }
                            />
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

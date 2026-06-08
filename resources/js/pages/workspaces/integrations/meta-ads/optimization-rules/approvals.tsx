import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { ArrowRight, Check, CheckCircle2, X } from 'lucide-react';
import { useState } from 'react';
import {
    executionModeLabel,
    metricLabel,
    type OptimizationProposal,
    optimizationRulesUrl,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    proposals: PaginatedData<OptimizationProposal>;
    query?: { page?: number | string; perPage?: number | string };
}

const actionVariant = (action: string) =>
    action === 'pause'
        ? 'destructive'
        : action === 'enable'
          ? 'default'
          : 'secondary';

const fmt = (v: string | null) =>
    v === null
        ? null
        : Number(v).toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });

const isBudget = (action: string) =>
    action === 'increase_budget' || action === 'decrease_budget';

export default function OptimizationApprovals({
    workspace,
    proposals,
    query,
}: Props) {
    const indexUrl = optimizationRulesUrl(workspace.slug);
    const [busyId, setBusyId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
        { title: 'Approvals', href: `${indexUrl}/approvals` },
    ];

    const navigate = (overrides: Record<string, unknown> = {}) =>
        router.get(
            `${indexUrl}/approvals`,
            {
                page: proposals.current_page,
                per_page: query?.perPage ?? proposals.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['proposals', 'query'],
            },
        );

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
                        <Badge variant={actionVariant(p.action)}>
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
                    <Button variant="outline" size="sm" asChild>
                        <Link href={indexUrl}>Back to rules</Link>
                    </Button>
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
                )}
            </div>
        </AppLayout>
    );
}

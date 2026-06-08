import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Check, CheckCircle2, X } from 'lucide-react';
import { useState } from 'react';
import {
    executionModeLabel,
    metricLabel,
    type OptimizationProposal,
    optimizationRulesUrl,
    timeWindowLabel,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    proposals: OptimizationProposal[];
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

export default function OptimizationApprovals({ workspace, proposals }: Props) {
    const indexUrl = optimizationRulesUrl(workspace.slug);
    const [busyId, setBusyId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
        { title: 'Approvals', href: `${indexUrl}/approvals` },
    ];

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

    const isBudget = (action: string) =>
        action === 'increase_budget' || action === 'decrease_budget';

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

                {proposals.length === 0 ? (
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
                    <div className="space-y-3">
                        {proposals.map((p) => (
                            <div
                                key={p.id}
                                className="rounded-2xl border border-black/[0.06] bg-white p-4 shadow-sm dark:border-white/[0.06] dark:bg-zinc-900"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                                {p.target_name ??
                                                    `${titleCase(p.target_type)} ${p.target_id}`}
                                            </span>
                                            <Badge
                                                variant={actionVariant(
                                                    p.action,
                                                )}
                                                className="capitalize"
                                            >
                                                {titleCase(p.action)}
                                            </Badge>
                                            {isBudget(p.action) &&
                                                fmt(p.current_value) !== null &&
                                                fmt(p.new_value) !== null && (
                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                        {fmt(p.current_value)}
                                                        <ArrowRight className="h-3 w-3 text-gray-400" />
                                                        {fmt(p.new_value)}
                                                    </span>
                                                )}
                                        </div>
                                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {p.rule?.name ?? 'Rule'} ·{' '}
                                            {p.ad_account?.name ??
                                                'Unknown account'}{' '}
                                            · {titleCase(p.target_type)}
                                            {p.rule && (
                                                <>
                                                    {' '}
                                                    ·{' '}
                                                    {executionModeLabel(
                                                        p.rule.execution_mode,
                                                    )}
                                                </>
                                            )}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={busyId === p.id}
                                            onClick={() => review(p, 'reject')}
                                        >
                                            <X className="mr-1 h-4 w-4" />
                                            Reject
                                        </Button>
                                        <Button
                                            size="sm"
                                            disabled={busyId === p.id}
                                            onClick={() => review(p, 'approve')}
                                        >
                                            <Check className="mr-1 h-4 w-4" />
                                            Approve
                                        </Button>
                                    </div>
                                </div>

                                {/* Why it triggered */}
                                <div className="mt-3 flex flex-wrap gap-1.5 border-t border-black/[0.04] pt-3 dark:border-white/[0.04]">
                                    {p.conditions_snapshot.map((c, i) => (
                                        <span
                                            key={i}
                                            className="inline-flex items-center gap-1 rounded-lg border border-black/[0.06] bg-gray-50 px-2 py-1 text-[11px] text-gray-600 dark:border-white/[0.06] dark:bg-white/[0.03] dark:text-gray-300"
                                        >
                                            {c.passed ? (
                                                <Check className="h-3 w-3 text-emerald-500" />
                                            ) : (
                                                <X className="h-3 w-3 text-gray-400" />
                                            )}
                                            <span className="font-medium text-gray-800 dark:text-gray-100">
                                                {metricLabel(c.metric)}
                                            </span>
                                            {c.operator} {c.threshold}
                                            <span className="text-gray-400">
                                                (actual:{' '}
                                                {c.actual_value === null
                                                    ? 'n/a'
                                                    : Number(
                                                          c.actual_value,
                                                      ).toLocaleString()}
                                                )
                                            </span>
                                            <span className="text-gray-400 dark:text-gray-500">
                                                ·{' '}
                                                {timeWindowLabel(c.time_window)}
                                            </span>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

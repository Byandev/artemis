import PageHeader from '@/components/common/PageHeader';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    History,
    Pencil,
    Plus,
    SlidersHorizontal,
    Trash2,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import {
    actionBadgeClass,
    executionModeLabel,
    isBudgetAction,
    type OptimizationRule,
    optimizationRulesUrl,
    scheduleLabel,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    rules: PaginatedData<OptimizationRule>;
    query?: { page?: number | string; perPage?: number | string };
}

function actionSummary(rule: OptimizationRule): string {
    if (!isBudgetAction(rule.action)) return titleCase(rule.action);
    const unit = rule.adjustment_type === 'percentage' ? '%' : '';
    const amount = rule.adjustment_value
        ? ` ${Number(rule.adjustment_value)}${unit}`
        : '';
    return `${titleCase(rule.action)}${amount}`;
}

export default function OptimizationRulesIndex({
    workspace,
    rules,
    query,
}: Props) {
    const [deleteTarget, setDeleteTarget] = useState<OptimizationRule | null>(
        null,
    );

    const indexUrl = optimizationRulesUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
    ];

    const navigate = (overrides: Record<string, unknown> = {}) =>
        router.get(
            indexUrl,
            {
                page: rules.current_page,
                per_page: query?.perPage ?? rules.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['rules', 'query'],
            },
        );

    const toggle = (rule: OptimizationRule) =>
        router.patch(
            `${indexUrl}/${rule.id}/toggle`,
            {},
            { preserveScroll: true },
        );

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`${indexUrl}/${deleteTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const columns: ColumnDef<OptimizationRule>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => {
                const rule = row.original;
                return (
                    <div className="flex min-w-0 items-center gap-2">
                        <span
                            className={cn(
                                'h-2 w-2 shrink-0 rounded-full',
                                rule.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300 dark:bg-gray-600',
                            )}
                        />
                        <Link
                            href={`${indexUrl}/${rule.id}/edit`}
                            className="truncate font-medium text-gray-800 hover:underline dark:text-gray-100"
                        >
                            {rule.name}
                        </Link>
                    </div>
                );
            },
        },
        {
            accessorKey: 'priority',
            header: 'Priority',
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                    {row.original.priority}
                </span>
            ),
        },
        {
            id: 'ad_accounts',
            header: 'Ad accounts',
            cell: ({ row }) => {
                const count = row.original.ad_accounts?.length ?? 0;
                return (
                    <span className="text-gray-700 dark:text-gray-200">
                        {count} ad account{count === 1 ? '' : 's'}
                    </span>
                );
            },
        },
        {
            id: 'conditions',
            header: 'Conditions',
            cell: ({ row }) => {
                const rule = row.original;
                const count = rule.conditions.length;
                return (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-gray-700 dark:text-gray-200">
                            {count} condition{count === 1 ? '' : 's'}
                        </span>
                        <span className="text-[10px] tracking-wide text-gray-400 uppercase">
                            {rule.condition_operator === 'and'
                                ? 'Match all'
                                : 'Match any'}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'action',
            header: 'Action',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={`gap-1 ${actionBadgeClass(row.original.action)}`}
                >
                    <Zap className="h-3 w-3" />
                    {actionSummary(row.original)}
                </Badge>
            ),
        },
        {
            id: 'mode',
            header: 'Mode',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px] font-medium">
                    {executionModeLabel(row.original.execution_mode)}
                </Badge>
            ),
        },
        {
            id: 'schedule',
            header: 'Schedule',
            cell: ({ row }) => (
                <span className="text-gray-500 dark:text-gray-400">
                    {scheduleLabel(
                        row.original.frequency,
                        row.original.run_at_hour,
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'logs_count',
            header: 'Triggered',
            cell: ({ row }) =>
                row.original.logs_count > 0 ? (
                    <Link
                        href={`${indexUrl}/logs?rule_id[]=${row.original.id}`}
                        className="text-emerald-600 hover:underline dark:text-emerald-400"
                    >
                        {row.original.logs_count}
                    </Link>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">0</span>
                ),
        },
        {
            id: 'active',
            header: 'Active',
            cell: ({ row }) => (
                <Switch
                    checked={row.original.is_active}
                    onCheckedChange={() => toggle(row.original)}
                />
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={`${indexUrl}/${row.original.id}/edit`}>
                            <Pencil className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleteTarget(row.original)}
                    >
                        <Trash2 className="h-4 w-4 text-gray-400 hover:text-red-500" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Optimization Rules" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Optimization Rules"
                    description="Automatically pause, enable, or adjust Meta Ads budgets based on performance."
                >
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`${indexUrl}/logs`}>
                            <History className="mr-1 h-4 w-4" />
                            History
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`${indexUrl}/approvals`}>Approvals</Link>
                    </Button>
                    <Button size="sm" asChild>
                        <Link href={`${indexUrl}/create`}>
                            <Plus className="mr-1 h-4 w-4" />
                            New Rule
                        </Link>
                    </Button>
                </PageHeader>

                {rules.total === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <SlidersHorizontal className="h-6 w-6" />
                        </div>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                            No optimization rules yet
                        </p>
                        <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                            Create a rule to automate budget and status changes
                            across your campaigns and ad sets.
                        </p>
                        <Button asChild className="mt-5" size="sm">
                            <Link href={`${indexUrl}/create`}>
                                <Plus className="mr-1 h-4 w-4" />
                                Create your first rule
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns as ColumnDef<unknown>[]}
                            data={(rules.data ?? []) as unknown[]}
                            meta={omit(rules, ['data'])}
                            onFetch={(params) =>
                                navigate({
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        rules.per_page,
                                })
                            }
                        />
                    </div>
                )}
            </div>

            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete optimization rule?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            “{deleteTarget?.name}” will be removed. Its trigger
                            history is kept. This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

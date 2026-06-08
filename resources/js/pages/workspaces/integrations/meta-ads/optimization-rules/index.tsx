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
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, SlidersHorizontal, Trash2, Zap } from 'lucide-react';
import { useState } from 'react';
import {
    isBudgetAction,
    metricLabel,
    type OptimizationRule,
    optimizationRulesUrl,
    timeWindowLabel,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    rules: OptimizationRule[];
}

const actionVariant = (action: string) =>
    action === 'pause'
        ? 'destructive'
        : action === 'enable'
          ? 'default'
          : 'secondary';

function actionSummary(rule: OptimizationRule): string {
    if (!isBudgetAction(rule.action)) return titleCase(rule.action);
    const unit = rule.adjustment_type === 'percentage' ? '%' : '';
    const amount = rule.adjustment_value
        ? ` ${Number(rule.adjustment_value)}${unit}`
        : '';
    return `${titleCase(rule.action)}${amount}`;
}

export default function OptimizationRulesIndex({ workspace, rules }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<OptimizationRule | null>(
        null,
    );

    const indexUrl = optimizationRulesUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
    ];

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Optimization Rules" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Optimization Rules"
                    description="Automatically pause, enable, or adjust Meta Ads budgets based on performance."
                >
                    <Button asChild>
                        <Link href={`${indexUrl}/create`}>
                            <Plus className="mr-1 h-4 w-4" />
                            New Rule
                        </Link>
                    </Button>
                </PageHeader>

                {rules.length === 0 ? (
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
                        <Button asChild className="mt-5">
                            <Link href={`${indexUrl}/create`}>
                                <Plus className="mr-1 h-4 w-4" />
                                Create your first rule
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {rules.map((rule) => (
                            <div
                                key={rule.id}
                                className={cn(
                                    'group rounded-2xl border border-black/[0.06] bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-white/[0.06] dark:bg-zinc-900',
                                    !rule.is_active && 'opacity-70',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 items-start gap-3">
                                        <span
                                            className={cn(
                                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                                rule.is_active
                                                    ? 'bg-emerald-500'
                                                    : 'bg-gray-300 dark:bg-gray-600',
                                            )}
                                        />
                                        <div className="min-w-0">
                                            <Link
                                                href={`${indexUrl}/${rule.id}/edit`}
                                                className="truncate text-sm font-semibold text-gray-800 hover:underline dark:text-gray-100"
                                            >
                                                {rule.name}
                                            </Link>
                                            <p className="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                                                {rule.ad_account?.name ??
                                                    'Unknown account'}{' '}
                                                · {titleCase(rule.target_type)}{' '}
                                                ·{' '}
                                                {rule.condition_operator ===
                                                'and'
                                                    ? 'Match all'
                                                    : 'Match any'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1.5">
                                        <Badge
                                            variant={actionVariant(rule.action)}
                                            className="gap-1"
                                        >
                                            <Zap className="h-3 w-3" />
                                            {actionSummary(rule)}
                                        </Badge>
                                        <Switch
                                            checked={rule.is_active}
                                            onCheckedChange={() => toggle(rule)}
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={`${indexUrl}/${rule.id}/edit`}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                setDeleteTarget(rule)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4 text-gray-400 hover:text-red-500" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="mt-3 flex flex-wrap items-center gap-1.5 pl-5">
                                    {rule.conditions.map((c, i) => (
                                        <span
                                            key={c.id ?? i}
                                            className="inline-flex items-center gap-1 rounded-lg border border-black/[0.06] bg-gray-50 px-2 py-1 text-[11px] text-gray-600 dark:border-white/[0.06] dark:bg-white/[0.03] dark:text-gray-300"
                                        >
                                            <span className="font-medium text-gray-800 dark:text-gray-100">
                                                {metricLabel(c.metric)}
                                            </span>
                                            {c.operator} {Number(c.value)}
                                            <span className="text-gray-400 dark:text-gray-500">
                                                ·{' '}
                                                {timeWindowLabel(c.time_window)}
                                            </span>
                                        </span>
                                    ))}
                                    <span className="ml-auto text-[11px] text-gray-400 dark:text-gray-500">
                                        Triggered {rule.logs_count}×
                                    </span>
                                </div>
                            </div>
                        ))}
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

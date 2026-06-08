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
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    Flame,
    Layers,
    Pencil,
    Plus,
    SlidersHorizontal,
    Trash2,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import {
    executionModeLabel,
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

/** Visual accent per action, used for the card spine and the action pill. */
interface Accent {
    spine: string;
    pill: string;
    icon: string;
}

const NEUTRAL_ACCENT: Accent = {
    spine: 'from-zinc-300 to-zinc-400 dark:from-zinc-600 dark:to-zinc-500',
    pill: 'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-300',
    icon: 'text-zinc-500',
};

const ACTION_ACCENT: Record<string, Accent> = {
    pause: {
        spine: 'from-rose-500 to-red-500',
        pill: 'bg-rose-500/10 text-rose-600 ring-rose-500/25 dark:text-rose-400',
        icon: 'text-rose-500',
    },
    enable: {
        spine: 'from-emerald-500 to-teal-500',
        pill: 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/25 dark:text-emerald-400',
        icon: 'text-emerald-500',
    },
    increase_budget: {
        spine: 'from-sky-500 to-indigo-500',
        pill: 'bg-sky-500/10 text-sky-600 ring-sky-500/25 dark:text-sky-400',
        icon: 'text-sky-500',
    },
    decrease_budget: {
        spine: 'from-amber-500 to-orange-500',
        pill: 'bg-amber-500/10 text-amber-600 ring-amber-500/25 dark:text-amber-400',
        icon: 'text-amber-500',
    },
};

const accentFor = (action: string): Accent =>
    ACTION_ACCENT[action] ?? NEUTRAL_ACCENT;

function actionSummary(rule: OptimizationRule): string {
    if (!isBudgetAction(rule.action)) return titleCase(rule.action);
    const unit = rule.adjustment_type === 'percentage' ? '%' : '';
    const amount = rule.adjustment_value
        ? ` ${Number(rule.adjustment_value)}${unit}`
        : '';
    return `${titleCase(rule.action)}${amount}`;
}

function StatTile({
    icon: Icon,
    label,
    value,
    tint,
}: {
    icon: typeof Layers;
    label: string;
    value: number;
    tint: string;
}) {
    return (
        <div className="relative overflow-hidden rounded-2xl border border-black/[0.06] bg-white p-4 shadow-sm dark:border-white/[0.06] dark:bg-zinc-900">
            <div className="flex items-center gap-3">
                <div
                    className={cn(
                        'flex h-10 w-10 items-center justify-center rounded-xl ring-1',
                        tint,
                    )}
                >
                    <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        {label}
                    </p>
                    <p className="text-xl font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                        {value.toLocaleString()}
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function OptimizationRulesIndex({ workspace, rules }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<OptimizationRule | null>(
        null,
    );

    const indexUrl = optimizationRulesUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
    ];

    const activeCount = rules.filter((r) => r.is_active).length;
    const triggeredTotal = rules.reduce(
        (sum, r) => sum + (r.logs_count || 0),
        0,
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Optimization Rules" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Optimization Rules"
                    description="Automatically pause, enable, or adjust Meta Ads budgets based on performance."
                >
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
                        <Button asChild className="mt-5" size={'sm'}>
                            <Link href={`${indexUrl}/create`}>
                                <Plus className="mr-1 h-4 w-4" />
                                Create your first rule
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <StatTile
                                icon={Layers}
                                label="Total rules"
                                value={rules.length}
                                tint="bg-indigo-500/10 text-indigo-600 ring-indigo-500/20 dark:text-indigo-400"
                            />
                            <StatTile
                                icon={Activity}
                                label="Active"
                                value={activeCount}
                                tint="bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:text-emerald-400"
                            />
                            <StatTile
                                icon={Flame}
                                label="Total triggers"
                                value={triggeredTotal}
                                tint="bg-amber-500/10 text-amber-600 ring-amber-500/20 dark:text-amber-400"
                            />
                        </div>

                        <div className="space-y-3">
                            {rules.map((rule) => {
                                const accent = accentFor(rule.action);
                                return (
                                    <div
                                        key={rule.id}
                                        className={cn(
                                            'group relative overflow-hidden rounded-2xl border border-black/[0.06] bg-white shadow-sm ring-1 ring-transparent transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:ring-black/[0.04] dark:border-white/[0.06] dark:bg-zinc-900 dark:hover:ring-white/[0.06]',
                                            !rule.is_active && 'opacity-65',
                                        )}
                                    >
                                        {/* Action-keyed accent spine */}
                                        <span
                                            className={cn(
                                                'absolute inset-y-0 left-0 w-1 bg-gradient-to-b',
                                                accent.spine,
                                            )}
                                        />

                                        <div className="p-4 pl-5">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex min-w-0 items-start gap-3">
                                                    {rule.is_active ? (
                                                        <span className="relative mt-1.5 flex h-2.5 w-2.5 shrink-0">
                                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                                                            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500" />
                                                        </span>
                                                    ) : (
                                                        <span className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600" />
                                                    )}
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={`${indexUrl}/${rule.id}/edit`}
                                                            className="block truncate text-sm font-semibold text-gray-800 transition-colors hover:text-emerald-600 dark:text-gray-100 dark:hover:text-emerald-400"
                                                        >
                                                            {rule.name}
                                                        </Link>
                                                        <p className="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                                                            {rule.ad_accounts
                                                                ?.map(
                                                                    (a) =>
                                                                        a.name,
                                                                )
                                                                .join(', ') ||
                                                                'No ad accounts'}{' '}
                                                            ·{' '}
                                                            {titleCase(
                                                                rule.target_type,
                                                            )}{' '}
                                                            ·{' '}
                                                            {rule.condition_operator ===
                                                            'and'
                                                                ? 'Match all'
                                                                : 'Match any'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1.5">
                                                    <span className="hidden items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 ring-1 ring-black/[0.04] sm:inline-flex dark:bg-white/[0.04] dark:text-gray-400 dark:ring-white/[0.06]">
                                                        {executionModeLabel(
                                                            rule.execution_mode,
                                                        )}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1',
                                                            accent.pill,
                                                        )}
                                                    >
                                                        <Zap className="h-3 w-3" />
                                                        {actionSummary(rule)}
                                                    </span>
                                                    <Switch
                                                        checked={rule.is_active}
                                                        onCheckedChange={() =>
                                                            toggle(rule)
                                                        }
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
                                                            setDeleteTarget(
                                                                rule,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-gray-400 transition-colors hover:text-red-500" />
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex flex-wrap items-center gap-1.5 pl-5">
                                                {rule.conditions.map((c, i) => (
                                                    <span
                                                        key={c.id ?? i}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-black/[0.06] bg-gradient-to-b from-gray-50 to-gray-100/60 px-2 py-1 text-[11px] text-gray-600 dark:border-white/[0.06] dark:from-white/[0.04] dark:to-white/[0.02] dark:text-gray-300"
                                                    >
                                                        <span className="font-semibold text-gray-800 dark:text-gray-100">
                                                            {metricLabel(
                                                                c.metric,
                                                            )}
                                                        </span>
                                                        <span className="font-medium text-gray-500 dark:text-gray-400">
                                                            {c.operator}{' '}
                                                            {Number(c.value)}
                                                        </span>
                                                        <span className="text-gray-400 dark:text-gray-500">
                                                            ·{' '}
                                                            {timeWindowLabel(
                                                                c.time_window,
                                                            )}
                                                        </span>
                                                    </span>
                                                ))}
                                                <span className="ml-auto inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 dark:text-gray-500">
                                                    <Flame className="h-3 w-3" />
                                                    Triggered {rule.logs_count}×
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
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

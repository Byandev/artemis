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
import { ArrowRight, Check, ChevronDown, History, X } from 'lucide-react';
import { useState } from 'react';
import {
    actionBadgeClass,
    type AdAccountOption,
    metricLabel,
    type OptimizationRuleLog,
    optimizationRulesUrl,
    titleCase,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    logs: PaginatedData<OptimizationRuleLog>;
    rules: AdAccountOption[];
    actions: string[];
    query?: {
        page?: number | string;
        perPage?: number | string;
        ruleIds?: string[];
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

function formatWhen(ts: string | null): { absolute: string; relative: string } {
    if (!ts) return { absolute: '—', relative: '' };
    const d = new Date(ts);
    const diff = (Date.now() - d.getTime()) / 1000;
    let relative: string;
    if (diff < 60) relative = `${Math.round(diff)}s ago`;
    else if (diff < 3600) relative = `${Math.round(diff / 60)}m ago`;
    else if (diff < 86400) relative = `${Math.round(diff / 3600)}h ago`;
    else relative = `${Math.round(diff / 86400)}d ago`;
    return { absolute: d.toLocaleString(), relative };
}

export default function OptimizationLogs({
    workspace,
    logs,
    rules,
    actions,
    query,
}: Props) {
    const indexUrl = optimizationRulesUrl(workspace.slug);
    const [ruleIds, setRuleIds] = useState<string[]>(query?.ruleIds ?? []);
    const [actionFilters, setActionFilters] = useState<string[]>(
        query?.actions ?? [],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
        { title: 'History', href: `${indexUrl}/logs` },
    ];

    const navigate = (overrides: Record<string, unknown> = {}) =>
        router.get(
            `${indexUrl}/logs`,
            {
                page: logs.current_page,
                per_page: query?.perPage ?? logs.per_page,
                rule_id: ruleIds.length ? ruleIds : undefined,
                action: actionFilters.length ? actionFilters : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['logs', 'rules', 'actions', 'query'],
            },
        );

    const onRules = (next: string[]) => {
        setRuleIds(next);
        navigate({ page: 1, rule_id: next.length ? next : undefined });
    };
    const onActions = (next: string[]) => {
        setActionFilters(next);
        navigate({ page: 1, action: next.length ? next : undefined });
    };

    const columns: ColumnDef<OptimizationRuleLog>[] = [
        {
            id: 'when',
            header: 'When',
            cell: ({ row }) => {
                const { absolute, relative } = formatWhen(
                    row.original.triggered_at,
                );
                return (
                    <div className="flex flex-col">
                        <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                            {relative}
                        </span>
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {absolute}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'rule',
            header: 'Rule',
            cell: ({ row }) => (
                <span className="text-gray-700 dark:text-gray-200">
                    {row.original.rule?.name ?? (
                        <span className="text-gray-400 italic">
                            Deleted rule
                        </span>
                    )}
                </span>
            ),
        },
        {
            id: 'target',
            header: 'Target',
            cell: ({ row }) => {
                const log = row.original;
                return (
                    <div className="min-w-0">
                        <div className="truncate font-medium text-gray-800 dark:text-gray-100">
                            {log.target_name ??
                                `${titleCase(log.target_type)} ${log.target_id}`}
                        </div>
                        <div className="text-[11px] text-gray-400">
                            {titleCase(log.target_type)}
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'action',
            header: 'Action',
            cell: ({ row }) => {
                const log = row.original;
                return (
                    <div className="flex flex-col items-start gap-1">
                        <Badge
                            variant="outline"
                            className={actionBadgeClass(log.action_taken)}
                        >
                            {titleCase(log.action_taken)}
                        </Badge>
                        {isBudget(log.action_taken) &&
                            fmt(log.previous_value) !== null &&
                            fmt(log.new_value) !== null && (
                                <span className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-600 dark:text-gray-300">
                                    {fmt(log.previous_value)}
                                    <ArrowRight className="h-3 w-3 text-gray-400" />
                                    {fmt(log.new_value)}
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
                            <span className="text-gray-400">
                                · {titleCase(c.time_window)}
                            </span>
                        </span>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Optimization History" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Optimization History"
                    description="Every change optimization rules have made across your campaigns and ad sets."
                >
                    <MultiFilter
                        label="rules"
                        options={rules.map((r) => ({
                            value: r.id,
                            label: r.name,
                        }))}
                        selected={ruleIds}
                        onChange={onRules}
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

                {logs.total === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <History className="h-6 w-6" />
                        </div>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                            No history yet
                        </p>
                        <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                            Actions taken by your optimization rules will appear
                            here once they start firing.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns as ColumnDef<unknown>[]}
                            data={(logs.data ?? []) as unknown[]}
                            meta={omit(logs, ['data'])}
                            onFetch={(params) =>
                                navigate({
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        logs.per_page,
                                })
                            }
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

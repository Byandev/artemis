import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Filter, Plus, Target, Trash2, Zap } from 'lucide-react';
import { type ReactNode } from 'react';
import {
    adjustmentTypeLabel,
    isBudgetAction,
    metricLabel,
    optimizationRulesUrl,
    timeWindowLabel,
    titleCase,
    type OptimizationRule,
    type RuleCondition,
    type RuleOptions,
} from './types';

interface Props {
    mode: 'create' | 'edit';
    workspace: { id: number; name: string; slug: string };
    rule: OptimizationRule | null;
    options: RuleOptions;
}

interface FormShape {
    name: string;
    meta_ads_account_id: string;
    target_type: string;
    condition_operator: string;
    action: string;
    adjustment_type: string;
    adjustment_value: string;
    max_adjustment_amount: string;
    budget_min: string;
    budget_max: string;
    is_active: boolean;
    conditions: RuleCondition[];
}

const emptyCondition = (options: RuleOptions): RuleCondition => ({
    metric: options.metrics[0],
    operator: options.operators[0],
    value: '',
    time_window: options.timeWindows[0],
});

function Section({
    icon,
    title,
    description,
    children,
}: {
    icon: ReactNode;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl border border-black/[0.06] bg-white shadow-sm dark:border-white/[0.06] dark:bg-zinc-900">
            <div className="flex items-start gap-3 border-b border-black/[0.04] p-5 dark:border-white/[0.04]">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    {icon}
                </div>
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {title}
                    </h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        {description}
                    </p>
                </div>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
            </Label>
            {children}
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function RuleForm({ mode, workspace, rule, options }: Props) {
    const isEdit = mode === 'edit';
    const indexUrl = optimizationRulesUrl(workspace.slug);

    const form = useForm<FormShape>({
        name: rule?.name ?? '',
        meta_ads_account_id:
            rule?.meta_ads_account_id ?? options.adAccounts[0]?.id ?? '',
        target_type: rule?.target_type ?? options.targetTypes[0],
        condition_operator:
            rule?.condition_operator ?? options.conditionOperators[0],
        action: rule?.action ?? options.actions[0],
        adjustment_type: rule?.adjustment_type ?? options.adjustmentTypes[0],
        adjustment_value: rule?.adjustment_value ?? '',
        max_adjustment_amount: rule?.max_adjustment_amount ?? '',
        budget_min: rule?.budget_min ?? '',
        budget_max: rule?.budget_max ?? '',
        is_active: rule?.is_active ?? true,
        conditions: rule?.conditions?.map((c) => ({
            metric: c.metric,
            operator: c.operator,
            value: c.value,
            time_window: c.time_window,
        })) ?? [emptyCondition(options)],
    });

    const { data, setData, processing, errors } = form;
    const budgetAction = isBudgetAction(data.action);

    const setCondition = (
        index: number,
        key: keyof RuleCondition,
        value: string,
    ) =>
        setData(
            'conditions',
            data.conditions.map((c, i) =>
                i === index ? { ...c, [key]: value } : c,
            ),
        );

    const addCondition = () =>
        setData('conditions', [...data.conditions, emptyCondition(options)]);

    const removeCondition = (index: number) =>
        setData(
            'conditions',
            data.conditions.filter((_, i) => i !== index),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((payload) => ({
            ...payload,
            adjustment_type: isBudgetAction(payload.action)
                ? payload.adjustment_type
                : null,
            adjustment_value: isBudgetAction(payload.action)
                ? payload.adjustment_value
                : null,
            // Only meaningful for percentage adjustments.
            max_adjustment_amount:
                isBudgetAction(payload.action) &&
                payload.adjustment_type === 'percentage'
                    ? payload.max_adjustment_amount
                    : null,
            budget_min: isBudgetAction(payload.action)
                ? payload.budget_min
                : null,
            budget_max: isBudgetAction(payload.action)
                ? payload.budget_max
                : null,
        }));

        if (isEdit && rule) {
            form.put(`${indexUrl}/${rule.id}`, { preserveScroll: true });
        } else {
            form.post(indexUrl, { preserveScroll: true });
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Optimization Rules', href: indexUrl },
        { title: isEdit ? 'Edit' : 'New', href: '#' },
    ];

    const targetLabel = titleCase(data.target_type).toLowerCase();
    const matchLabel = data.condition_operator === 'and' ? 'all' : 'any';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    isEdit ? 'Edit optimization rule' : 'New optimization rule'
                }
            />

            <form onSubmit={submit}>
                {/* Sticky action bar */}
                <div className="sticky top-0 z-10 border-b border-black/[0.06] bg-white/80 backdrop-blur-md dark:border-white/[0.06] dark:bg-zinc-900/80">
                    <div className="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <Link
                                href={indexUrl}
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-black/[0.04] hover:text-gray-600 dark:hover:bg-white/[0.04]"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <div className="min-w-0">
                                <h1 className="truncate text-[17px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                                    {isEdit
                                        ? 'Edit optimization rule'
                                        : 'New optimization rule'}
                                </h1>
                                <p className="truncate text-[11px] text-gray-400 dark:text-gray-500">
                                    {workspace.name} · Meta Ads automation
                                </p>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={indexUrl}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                {isEdit ? 'Save changes' : 'Create rule'}
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="space-y-5 px-4 py-6 sm:px-6">
                    {/* Live summary */}
                    <div className="rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.07] to-transparent p-4 text-sm text-gray-600 dark:text-gray-300">
                        <span className="font-medium text-gray-800 dark:text-gray-100">
                            {data.name || 'This rule'}
                        </span>{' '}
                        will{' '}
                        <span className="font-medium text-emerald-700 dark:text-emerald-400">
                            {titleCase(data.action).toLowerCase()}
                            {budgetAction && data.adjustment_value
                                ? ` by ${data.adjustment_value}${data.adjustment_type === 'percentage' ? '%' : ''}`
                                : ''}
                        </span>{' '}
                        each {targetLabel} where {matchLabel} of{' '}
                        {data.conditions.length}{' '}
                        {data.conditions.length === 1
                            ? 'condition'
                            : 'conditions'}{' '}
                        match.
                    </div>

                    <Section
                        icon={<Target className="h-4 w-4" />}
                        title="Basics"
                        description="Name your rule and choose what it applies to."
                    >
                        <div className="space-y-4">
                            <Field label="Rule name" error={errors.name}>
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Pause low-ROAS campaigns"
                                />
                            </Field>
                            <Field
                                label="Ad account"
                                error={errors.meta_ads_account_id}
                            >
                                {options.adAccounts.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-black/10 px-3 py-2.5 text-xs text-gray-400 dark:border-white/10 dark:text-gray-500">
                                        No synced ad accounts in this workspace.
                                        Connect one under Meta Ads → Ad Accounts
                                        first.
                                    </p>
                                ) : (
                                    <Select
                                        value={data.meta_ads_account_id}
                                        onValueChange={(v) =>
                                            setData('meta_ads_account_id', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an ad account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.adAccounts.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={a.id}
                                                >
                                                    {a.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Applies to">
                                    <Select
                                        value={data.target_type}
                                        onValueChange={(v) =>
                                            setData('target_type', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.targetTypes.map((t) => (
                                                <SelectItem key={t} value={t}>
                                                    {titleCase(t)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field label="Condition matching">
                                    <Select
                                        value={data.condition_operator}
                                        onValueChange={(v) =>
                                            setData('condition_operator', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="and">
                                                Match all (AND)
                                            </SelectItem>
                                            <SelectItem value="or">
                                                Match any (OR)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </div>
                        </div>
                    </Section>

                    <Section
                        icon={<Filter className="h-4 w-4" />}
                        title="Conditions"
                        description="The rule fires when these performance thresholds are met."
                    >
                        <div className="space-y-3">
                            {data.conditions.map((condition, index) => (
                                <div key={index}>
                                    {index > 0 && (
                                        <div className="flex items-center gap-3 py-1.5">
                                            <span className="rounded-md bg-black/[0.04] px-2 py-0.5 text-[10px] font-semibold tracking-wide text-gray-500 uppercase dark:bg-white/[0.06]">
                                                {data.condition_operator}
                                            </span>
                                            <span className="h-px flex-1 bg-black/[0.05] dark:bg-white/[0.05]" />
                                        </div>
                                    )}
                                    <div className="grid grid-cols-2 gap-2 rounded-xl border border-black/[0.06] bg-gray-50/50 p-2.5 sm:grid-cols-[1.4fr_auto_1fr_1.6fr_auto] sm:items-center dark:border-white/[0.06] dark:bg-white/[0.02]">
                                        <Select
                                            value={condition.metric}
                                            onValueChange={(v) =>
                                                setCondition(index, 'metric', v)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.metrics.map((m) => (
                                                    <SelectItem
                                                        key={m}
                                                        value={m}
                                                    >
                                                        {metricLabel(m)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            value={condition.operator}
                                            onValueChange={(v) =>
                                                setCondition(
                                                    index,
                                                    'operator',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full sm:w-16">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.operators.map((o) => (
                                                    <SelectItem
                                                        key={o}
                                                        value={o}
                                                    >
                                                        {o}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            type="number"
                                            step="any"
                                            value={String(condition.value)}
                                            onChange={(e) =>
                                                setCondition(
                                                    index,
                                                    'value',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Value"
                                        />
                                        <Select
                                            value={condition.time_window}
                                            onValueChange={(v) =>
                                                setCondition(
                                                    index,
                                                    'time_window',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.timeWindows.map(
                                                    (w) => (
                                                        <SelectItem
                                                            key={w}
                                                            value={w}
                                                        >
                                                            {timeWindowLabel(w)}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="justify-self-end"
                                            disabled={
                                                data.conditions.length === 1
                                            }
                                            onClick={() =>
                                                removeCondition(index)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4 text-gray-400 hover:text-red-500" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {errors.conditions && (
                                <p className="text-xs text-red-500">
                                    {errors.conditions}
                                </p>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addCondition}
                                className="w-full border-dashed"
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add condition
                            </Button>
                        </div>
                    </Section>

                    <Section
                        icon={<Zap className="h-4 w-4" />}
                        title="Action"
                        description="What happens when the conditions are met."
                    >
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Action">
                                    <Select
                                        value={data.action}
                                        onValueChange={(v) =>
                                            setData('action', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.actions.map((a) => (
                                                <SelectItem key={a} value={a}>
                                                    {titleCase(a)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                {budgetAction && (
                                    <Field label="Adjustment type">
                                        <Select
                                            value={data.adjustment_type}
                                            onValueChange={(v) =>
                                                setData('adjustment_type', v)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.adjustmentTypes.map(
                                                    (t) => (
                                                        <SelectItem
                                                            key={t}
                                                            value={t}
                                                        >
                                                            {adjustmentTypeLabel(
                                                                t,
                                                            )}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                )}
                            </div>

                            {budgetAction && (
                                <>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Field
                                            label={
                                                data.adjustment_type ===
                                                'percentage'
                                                    ? 'Percentage (%)'
                                                    : 'Amount'
                                            }
                                            error={errors.adjustment_value}
                                        >
                                            <Input
                                                type="number"
                                                step="any"
                                                value={data.adjustment_value}
                                                onChange={(e) =>
                                                    setData(
                                                        'adjustment_value',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        {data.adjustment_type ===
                                            'percentage' && (
                                            <Field
                                                label={
                                                    data.action ===
                                                    'decrease_budget'
                                                        ? 'Max decrease amount'
                                                        : 'Max increase amount'
                                                }
                                                error={
                                                    errors.max_adjustment_amount
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    value={
                                                        data.max_adjustment_amount
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'max_adjustment_amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="No cap"
                                                />
                                            </Field>
                                        )}
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Field label="Min budget">
                                            <Input
                                                type="number"
                                                step="any"
                                                value={data.budget_min}
                                                onChange={(e) =>
                                                    setData(
                                                        'budget_min',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Max budget"
                                            error={errors.budget_max}
                                        >
                                            <Input
                                                type="number"
                                                step="any"
                                                value={data.budget_max}
                                                onChange={(e) =>
                                                    setData(
                                                        'budget_max',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                </>
                            )}

                            <div
                                className={cn(
                                    'flex items-center justify-between rounded-xl border border-black/[0.06] p-3.5 dark:border-white/[0.06]',
                                    data.is_active &&
                                        'border-emerald-500/30 bg-emerald-500/[0.04]',
                                )}
                            >
                                <div>
                                    <p className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                        Active
                                    </p>
                                    <p className="text-xs text-gray-400 dark:text-gray-500">
                                        Inactive rules are saved but never run.
                                    </p>
                                </div>
                                <Switch
                                    checked={data.is_active}
                                    onCheckedChange={(v) =>
                                        setData('is_active', v)
                                    }
                                />
                            </div>
                        </div>
                    </Section>
                </div>
            </form>
        </AppLayout>
    );
}

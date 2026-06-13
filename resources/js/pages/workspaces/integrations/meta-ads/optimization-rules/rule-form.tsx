import PageHeader from '@/components/common/PageHeader';
import { MultiSelect } from '@/components/ui/multi-select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { type ReactNode } from 'react';
import {
    adjustmentTypeLabel,
    frequencyLabel,
    hourLabel,
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
    selectedAdAccountIds: string[];
    options: RuleOptions;
}

interface FormShape {
    name: string;
    meta_ads_account_ids: string[];
    target_type: string;
    condition_operator: string;
    action: string;
    adjustment_type: string;
    adjustment_value: string;
    max_adjustment_amount: string;
    budget_min: string;
    budget_max: string;
    is_active: boolean;
    execution_mode: string;
    priority: string;
    frequency: string;
    run_at_hour: number;
    conditions: RuleCondition[];
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const errorClass = 'font-mono text-[11px] text-red-500';
const sectionClass =
    'rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900';
const sectionLabelClass =
    'mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600';

// Execution modes shown but not selectable for now (monitoring proposals first).
const DISABLED_EXECUTION_MODES = new Set(['automatic']);

const emptyCondition = (options: RuleOptions): RuleCondition => ({
    metric: options.metrics[0],
    operator: options.operators[0],
    value: '',
    time_window: options.timeWindows[0],
});

function Field({
    label,
    required,
    error,
    className,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label className={labelClass}>
                {label}
                {required && <span className="text-red-400"> *</span>}
            </label>
            {children}
            {error && <p className={errorClass}>{error}</p>}
        </div>
    );
}

export default function RuleForm({
    mode,
    workspace,
    rule,
    selectedAdAccountIds,
    options,
}: Props) {
    const isEdit = mode === 'edit';
    const indexUrl = optimizationRulesUrl(workspace.slug);

    const form = useForm<FormShape>({
        name: rule?.name ?? '',
        meta_ads_account_ids: selectedAdAccountIds,
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
        // Fall back to approval when the rule's mode is disabled (automatic).
        execution_mode:
            rule?.execution_mode &&
            !DISABLED_EXECUTION_MODES.has(rule.execution_mode)
                ? rule.execution_mode
                : options.executionModes[0],
        priority: rule?.priority != null ? String(rule.priority) : '0',
        frequency: rule?.frequency ?? 'daily',
        run_at_hour: rule?.run_at_hour ?? 9,
        conditions: rule?.conditions?.map((c) => ({
            metric: c.metric,
            operator: c.operator,
            value: c.value,
            time_window: c.time_window,
        })) ?? [emptyCondition(options)],
    });

    const { data, setData, processing, errors } = form;
    const budgetAction = isBudgetAction(data.action);
    const isPercentage = data.adjustment_type === 'percentage';

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${workspace.name} - ${isEdit ? 'Edit' : 'Create'} Optimization Rule`}
            />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title={
                        isEdit
                            ? 'Edit Optimization Rule'
                            : 'Create Optimization Rule'
                    }
                    description="Automatically pause, enable, or adjust Meta Ads budgets based on performance."
                >
                    <Link
                        href={indexUrl}
                        className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Rules
                    </Link>
                </PageHeader>

                <form onSubmit={submit} className="space-y-5">
                    {/* Basics */}
                    <div className={sectionClass}>
                        <p className={sectionLabelClass}>Basics</p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field
                                label="Rule Name"
                                required
                                error={errors.name}
                            >
                                <input
                                    type="text"
                                    className={inputClass}
                                    placeholder="e.g. Pause low-ROAS campaigns"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Ad Accounts"
                                required
                                error={
                                    errors.meta_ads_account_ids ??
                                    errors['meta_ads_account_ids.0']
                                }
                            >
                                {options.adAccounts.length === 0 ? (
                                    <p className="rounded-[10px] border border-dashed border-black/10 px-3 py-2.5 font-mono text-[11px] text-gray-400 dark:border-white/10 dark:text-gray-500">
                                        No synced ad accounts. Connect one under
                                        Meta Ads → Ad Accounts first.
                                    </p>
                                ) : (
                                    <MultiSelect
                                        options={options.adAccounts.map(
                                            (a) => ({
                                                value: a.id,
                                                label: a.name,
                                            }),
                                        )}
                                        selected={data.meta_ads_account_ids}
                                        onChange={(v) =>
                                            setData('meta_ads_account_ids', v)
                                        }
                                        placeholder="Select ad accounts"
                                    />
                                )}
                            </Field>

                            <Field label="Applies To" required>
                                <select
                                    className={inputClass}
                                    value={data.target_type}
                                    onChange={(e) =>
                                        setData('target_type', e.target.value)
                                    }
                                >
                                    {options.targetTypes.map((t) => (
                                        <option key={t} value={t}>
                                            {titleCase(t)}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Condition Matching" required>
                                <select
                                    className={inputClass}
                                    value={data.condition_operator}
                                    onChange={(e) =>
                                        setData(
                                            'condition_operator',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="and">Match all (AND)</option>
                                    <option value="or">Match any (OR)</option>
                                </select>
                            </Field>

                            <Field label="Priority" error={errors.priority}>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    className={inputClass}
                                    placeholder="0"
                                    value={data.priority}
                                    onChange={(e) =>
                                        setData('priority', e.target.value)
                                    }
                                />
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Higher wins when two rules target the same
                                    campaign or ad set in a run.
                                </p>
                            </Field>
                        </div>
                    </div>

                    {/* Conditions */}
                    <div className={sectionClass}>
                        <div className="mb-5 flex items-center justify-between">
                            <p className="font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                                Conditions
                            </p>
                            <button
                                type="button"
                                onClick={addCondition}
                                className="flex h-8 items-center gap-1 rounded-lg border border-black/8 bg-stone-100 px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Add condition
                            </button>
                        </div>

                        <div className="space-y-3">
                            {data.conditions.map((condition, index) => (
                                <div key={index}>
                                    {index > 0 && (
                                        <div className="flex items-center gap-3 py-1.5">
                                            <span className="rounded-md bg-black/[0.04] px-2 py-0.5 font-mono text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:bg-white/[0.06]">
                                                {data.condition_operator}
                                            </span>
                                            <span className="h-px flex-1 bg-black/[0.05] dark:bg-white/[0.05]" />
                                        </div>
                                    )}
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1.4fr_auto_1fr_1.6fr_auto] sm:items-center">
                                        <select
                                            className={inputClass}
                                            value={condition.metric}
                                            onChange={(e) =>
                                                setCondition(
                                                    index,
                                                    'metric',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {options.metrics.map((m) => (
                                                <option key={m} value={m}>
                                                    {metricLabel(m)}
                                                </option>
                                            ))}
                                        </select>
                                        <select
                                            className={cn(
                                                inputClass,
                                                'sm:w-16',
                                            )}
                                            value={condition.operator}
                                            onChange={(e) =>
                                                setCondition(
                                                    index,
                                                    'operator',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {options.operators.map((o) => (
                                                <option key={o} value={o}>
                                                    {o}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            step="any"
                                            className={inputClass}
                                            placeholder="Value"
                                            value={String(condition.value)}
                                            onChange={(e) =>
                                                setCondition(
                                                    index,
                                                    'value',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {condition.metric === 'budget' ||
                                        condition.metric === 'running_days' ||
                                        condition.metric ===
                                            'last_modified_in_hours' ? (
                                            <div className="flex h-10 items-center rounded-[10px] border border-dashed border-black/8 bg-stone-50/60 px-3 font-mono! text-[11px]! text-gray-400 dark:border-white/8 dark:bg-zinc-800/60 dark:text-gray-500">
                                                {condition.metric ===
                                                'running_days'
                                                    ? 'Days running'
                                                    : condition.metric ===
                                                        'last_modified_in_hours'
                                                      ? 'Hours since edit'
                                                      : 'Current value'}
                                            </div>
                                        ) : (
                                            <select
                                                className={inputClass}
                                                value={condition.time_window}
                                                onChange={(e) =>
                                                    setCondition(
                                                        index,
                                                        'time_window',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {options.timeWindows.map(
                                                    (w) => (
                                                        <option
                                                            key={w}
                                                            value={w}
                                                        >
                                                            {timeWindowLabel(w)}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        )}
                                        <button
                                            type="button"
                                            disabled={
                                                data.conditions.length === 1
                                            }
                                            onClick={() =>
                                                removeCondition(index)
                                            }
                                            className="flex h-10 w-10 items-center justify-center justify-self-end rounded-[10px] text-gray-400 transition-colors hover:bg-red-500/10 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                            {errors.conditions && (
                                <p className={errorClass}>
                                    {errors.conditions}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Action */}
                    <div className={sectionClass}>
                        <p className={sectionLabelClass}>Action</p>
                        <div className="space-y-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field label="Action" required>
                                    <select
                                        className={inputClass}
                                        value={data.action}
                                        onChange={(e) =>
                                            setData('action', e.target.value)
                                        }
                                    >
                                        {options.actions.map((a) => (
                                            <option key={a} value={a}>
                                                {titleCase(a)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                {budgetAction && (
                                    <Field label="Adjustment Type">
                                        <select
                                            className={inputClass}
                                            value={data.adjustment_type}
                                            onChange={(e) =>
                                                setData(
                                                    'adjustment_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {options.adjustmentTypes.map(
                                                (t) => (
                                                    <option key={t} value={t}>
                                                        {adjustmentTypeLabel(t)}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </Field>
                                )}
                            </div>

                            {budgetAction && (
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        label={
                                            isPercentage
                                                ? 'Percentage (%)'
                                                : 'Amount'
                                        }
                                        error={errors.adjustment_value}
                                    >
                                        <input
                                            type="number"
                                            step="any"
                                            className={inputClass}
                                            value={data.adjustment_value}
                                            onChange={(e) =>
                                                setData(
                                                    'adjustment_value',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    {isPercentage && (
                                        <Field
                                            label={
                                                data.action ===
                                                'decrease_budget'
                                                    ? 'Max Decrease Amount'
                                                    : 'Max Increase Amount'
                                            }
                                        >
                                            <input
                                                type="number"
                                                step="any"
                                                className={inputClass}
                                                placeholder="No cap"
                                                value={
                                                    data.max_adjustment_amount
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'max_adjustment_amount',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    )}

                                    <Field label="Min Budget">
                                        <input
                                            type="number"
                                            step="any"
                                            className={inputClass}
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
                                        label="Max Budget"
                                        error={errors.budget_max}
                                    >
                                        <input
                                            type="number"
                                            step="any"
                                            className={inputClass}
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
                            )}

                            <Field label="When Conditions Are Met">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {options.executionModes.map((mode) => {
                                        const disabled =
                                            DISABLED_EXECUTION_MODES.has(mode);
                                        const selected =
                                            data.execution_mode === mode;
                                        return (
                                            <button
                                                key={mode}
                                                type="button"
                                                disabled={disabled}
                                                title={
                                                    disabled
                                                        ? 'Disabled for now — proposals are reviewed manually while we monitor.'
                                                        : undefined
                                                }
                                                onClick={() =>
                                                    setData(
                                                        'execution_mode',
                                                        mode,
                                                    )
                                                }
                                                className={cn(
                                                    'rounded-[10px] border p-3 text-left transition-all',
                                                    disabled
                                                        ? 'cursor-not-allowed border-black/8 bg-stone-100 opacity-60 dark:border-white/8 dark:bg-zinc-800/50'
                                                        : selected
                                                          ? 'border-emerald-500 bg-emerald-500/5 ring-2 ring-emerald-500/15'
                                                          : 'border-black/8 bg-stone-50 hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:hover:bg-zinc-700',
                                                )}
                                            >
                                                <p className="flex items-center gap-1.5 font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                                                    {mode === 'automatic'
                                                        ? 'Automatically apply'
                                                        : 'Require approval'}
                                                    {disabled && (
                                                        <span className="rounded bg-gray-200 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-gray-500 uppercase dark:bg-zinc-700 dark:text-gray-400">
                                                            Disabled
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    {mode === 'automatic'
                                                        ? 'Changes applied on the next run.'
                                                        : 'Proposed for review first.'}
                                                </p>
                                            </button>
                                        );
                                    })}
                                </div>
                            </Field>

                            <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-4 py-3 dark:border-white/8 dark:bg-zinc-800">
                                <div>
                                    <p className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                                        Active
                                    </p>
                                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
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
                    </div>

                    {/* Schedule */}
                    <div className={sectionClass}>
                        <p className={sectionLabelClass}>Schedule</p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field
                                label="Run frequency"
                                error={errors.frequency}
                            >
                                <select
                                    className={inputClass}
                                    value={data.frequency}
                                    onChange={(e) =>
                                        setData('frequency', e.target.value)
                                    }
                                >
                                    {options.frequencies.map((f) => (
                                        <option key={f} value={f}>
                                            {frequencyLabel(f)}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            {data.frequency === 'daily' && (
                                <Field
                                    label="Run at"
                                    error={errors.run_at_hour}
                                >
                                    <select
                                        className={inputClass}
                                        value={String(data.run_at_hour)}
                                        onChange={(e) =>
                                            setData(
                                                'run_at_hour',
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        {Array.from({ length: 24 }, (_, h) => (
                                            <option key={h} value={h}>
                                                {hourLabel(h)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}
                        </div>
                        <p className="mt-3 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            Schedules align to the clock — e.g. “Every 3 hours”
                            runs at 00:00, 03:00, 06:00 … Times use the server
                            timezone.
                        </p>
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => router.get(indexUrl)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Changes'
                                  : 'Create Rule'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

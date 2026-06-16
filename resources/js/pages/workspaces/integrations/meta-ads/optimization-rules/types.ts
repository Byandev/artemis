export interface RuleCondition {
    id?: number;
    metric: string;
    operator: string;
    value: string | number;
    time_window: string;
}

export interface AdAccountOption {
    id: string;
    name: string;
}

export interface OptimizationRule {
    id: number;
    name: string;
    ad_accounts?: AdAccountOption[];
    target_type: string;
    condition_operator: string;
    action: string;
    adjustment_type: string | null;
    adjustment_value: string | null;
    max_adjustment_amount: string | null;
    min_adjustment_amount: string | null;
    budget_min: string | null;
    budget_max: string | null;
    is_active: boolean;
    execution_mode: string;
    priority: number;
    frequency: string;
    run_at_hour: number | null;
    logs_count: number;
    conditions: RuleCondition[];
    // Whether the current user may edit/run/delete this rule (role permission +
    // manage access to every ad account the rule targets). Defaults to true for
    // unrestricted users / pages that don't supply it.
    can_manage?: boolean;
}

export interface ConditionSnapshot {
    metric: string;
    operator: string;
    threshold: number;
    actual_value: number | null;
    time_window: string;
    passed: boolean;
}

export interface OptimizationProposal {
    id: number;
    target_type: string;
    target_id: string;
    target_name: string | null;
    action: string;
    current_value: string | null;
    new_value: string | null;
    // Current budget of a pause/enable target (the spend it stops / resumes).
    target_budget?: number | string | null;
    conditions_snapshot: ConditionSnapshot[];
    status: string;
    created_at: string | null;
    rule?: { id: number; name: string; execution_mode: string };
    ad_account?: AdAccountOption;
}

export interface OptimizationRuleLog {
    id: number;
    rule?: { id: number; name: string } | null;
    target_type: string;
    target_id: string;
    target_name: string | null;
    action_taken: string;
    previous_value: string | null;
    new_value: string | null;
    conditions_snapshot: ConditionSnapshot[];
    triggered_at: string | null;
}

export interface RuleOptions {
    adAccounts: AdAccountOption[];
    metrics: string[];
    operators: string[];
    timeWindows: string[];
    actions: string[];
    targetTypes: string[];
    conditionOperators: string[];
    adjustmentTypes: string[];
    executionModes: string[];
    frequencies: string[];
}

export const BUDGET_ACTIONS = ['increase_budget', 'decrease_budget'];

export const isBudgetAction = (action: string): boolean =>
    BUDGET_ACTIONS.includes(action);

/**
 * A premium, distinct badge style per action — a soft tinted pill with an inset
 * ring and a subtle shadow. Standard palette classes, so it never depends on a
 * theme token being mapped. Pair with
 * `<Badge variant="outline" className={actionBadgeClass(action)}>`.
 */
export const actionBadgeClass = (action: string): string => {
    // rounded-full + ring-inset overrides the Badge's default square border.
    const base =
        'rounded-full border-transparent px-2.5 py-0.5 text-[11px] font-semibold tracking-tight shadow-sm ring-1 ring-inset';

    const tone: Record<string, string> = {
        pause: 'bg-gradient-to-b from-rose-50 to-rose-100 text-rose-700 ring-rose-600/20 shadow-rose-500/10 dark:from-rose-500/15 dark:to-rose-500/10 dark:text-rose-300 dark:ring-rose-400/25',
        enable: 'bg-gradient-to-b from-emerald-50 to-emerald-100 text-emerald-700 ring-emerald-600/20 shadow-emerald-500/10 dark:from-emerald-500/15 dark:to-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
        increase_budget:
            'bg-gradient-to-b from-sky-50 to-sky-100 text-sky-700 ring-sky-600/20 shadow-sky-500/10 dark:from-sky-500/15 dark:to-sky-500/10 dark:text-sky-300 dark:ring-sky-400/25',
        decrease_budget:
            'bg-gradient-to-b from-amber-50 to-amber-100 text-amber-800 ring-amber-600/20 shadow-amber-500/10 dark:from-amber-500/15 dark:to-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
    };

    return `${base} ${tone[action] ?? 'bg-gradient-to-b from-stone-50 to-stone-100 text-stone-700 ring-black/10 dark:from-zinc-800 dark:to-zinc-800/70 dark:text-zinc-300 dark:ring-white/10'}`;
};

const UPPERCASE_METRICS = new Set(['roas', 'cpa', 'cpc', 'ctr', 'cpm']);

/** Title-case an underscored enum value, e.g. last_7_days → "Last 7 Days". */
export const titleCase = (value: string): string =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export const metricLabel = (metric: string): string =>
    UPPERCASE_METRICS.has(metric) ? metric.toUpperCase() : titleCase(metric);

export const executionModeLabel = (mode: string): string =>
    mode === 'automatic' ? 'Automatic' : 'Approval';

export const FREQUENCY_LABELS: Record<string, string> = {
    hourly: 'Every hour',
    every_3_hours: 'Every 3 hours',
    every_6_hours: 'Every 6 hours',
    every_12_hours: 'Every 12 hours',
    daily: 'Daily',
};

export const frequencyLabel = (frequency: string): string =>
    FREQUENCY_LABELS[frequency] ?? titleCase(frequency);

export const hourLabel = (hour: number): string =>
    `${String(hour).padStart(2, '0')}:00`;

export const scheduleLabel = (
    frequency: string,
    runAtHour: number | null,
): string =>
    frequency === 'daily'
        ? `Daily at ${hourLabel(runAtHour ?? 0)}`
        : frequencyLabel(frequency);

export const adjustmentTypeLabel = (type: string): string => {
    if (type === 'percentage') return 'Percentage';
    if (type === 'fixed') return 'Specific amount';
    return titleCase(type);
};

export const TIME_WINDOW_LABELS: Record<string, string> = {
    today: 'Today',
    yesterday: 'Yesterday',
    last_3_days: 'Last 3 days (incl. today)',
    last_7_days: 'Last 7 days (incl. today)',
    previous_3_days: 'Previous 3 days',
    previous_7_days: 'Previous 7 days',
};

export const timeWindowLabel = (window: string): string =>
    TIME_WINDOW_LABELS[window] ?? titleCase(window);

export const optimizationRulesUrl = (slug: string): string =>
    `/workspaces/${slug}/integrations/meta/optimization-rules`;

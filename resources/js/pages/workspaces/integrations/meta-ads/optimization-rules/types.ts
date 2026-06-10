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
    budget_min: string | null;
    budget_max: string | null;
    is_active: boolean;
    execution_mode: string;
    priority: number;
    logs_count: number;
    conditions: RuleCondition[];
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
}

export const BUDGET_ACTIONS = ['increase_budget', 'decrease_budget'];

export const isBudgetAction = (action: string): boolean =>
    BUDGET_ACTIONS.includes(action);

/**
 * A distinct, readable badge colour per action. Uses standard palette classes
 * so it never depends on a theme token being mapped. Pair with
 * `<Badge variant="outline" className={actionBadgeClass(action)}>`.
 */
export const actionBadgeClass = (action: string): string => {
    switch (action) {
        case 'pause':
            return 'border-transparent bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400';
        case 'enable':
            return 'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400';
        case 'increase_budget':
            return 'border-transparent bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400';
        case 'decrease_budget':
            return 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400';
        default:
            return 'border-transparent bg-stone-100 text-stone-700 dark:bg-zinc-800 dark:text-zinc-300';
    }
};

const UPPERCASE_METRICS = new Set(['roas', 'cpa', 'cpc', 'ctr', 'cpm']);

/** Title-case an underscored enum value, e.g. last_7_days → "Last 7 Days". */
export const titleCase = (value: string): string =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export const metricLabel = (metric: string): string =>
    UPPERCASE_METRICS.has(metric) ? metric.toUpperCase() : titleCase(metric);

export const executionModeLabel = (mode: string): string =>
    mode === 'automatic' ? 'Automatic' : 'Approval';

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

import { OptimizationRuleCondition } from '@/types/models/OptimizationRule';
import { startCase } from 'lodash';
import { Filter, Zap } from 'lucide-react';

interface ConditionsBadgeProps {
    conditions: OptimizationRuleCondition[];
}

const getConditionColor = (metric: string) => {
    const colors: Record<string, { bg: string; text: string; border: string }> = {
        spend: { bg: 'bg-emerald-100 dark:bg-emerald-900', text: 'text-emerald-700 dark:text-emerald-300', border: 'border-emerald-200 dark:border-emerald-800' },
        impressions: { bg: 'bg-blue-100 dark:bg-blue-900', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-200 dark:border-blue-800' },
        clicks: { bg: 'bg-purple-100 dark:bg-purple-900', text: 'text-purple-700 dark:text-purple-300', border: 'border-purple-200 dark:border-purple-800' },
        sales: { bg: 'bg-rose-100 dark:bg-rose-900', text: 'text-rose-700 dark:text-rose-300', border: 'border-rose-200 dark:border-rose-800' },
        roas: { bg: 'bg-amber-100 dark:bg-amber-900', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-200 dark:border-amber-800' },
    };
    return colors[metric] || colors.spend;
};

export const ConditionsBadge = ({ conditions }: ConditionsBadgeProps) => {
    if (!conditions || conditions.length === 0) {
        return (
            <div className="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                <Filter className="h-4 w-4" />
                <span className="text-sm font-medium">No conditions</span>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            {conditions.map((condition, idx) => {
                const colors = getConditionColor(condition.metric);
                return (
                    <div
                        key={idx}
                        className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-linear-to-r ${colors.bg} ${colors.text} border ${colors.border} whitespace-nowrap cursor-default`}
                    >
                        <Zap className="h-3.5 w-3.5 opacity-70" />
                        <span className="text-xs font-semibold">{startCase(condition.metric)}</span>
                        <span className="text-xs opacity-80 font-medium">{startCase(condition.operator)}</span>
                        <span className="text-xs font-bold">{condition.value}</span>
                    </div>
                );
            })}
        </div>
    );
};

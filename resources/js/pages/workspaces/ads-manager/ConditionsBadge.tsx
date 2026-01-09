import { OptimizationRuleCondition } from '@/types/models/OptimizationRule';
import { Filter } from 'lucide-react';

interface ConditionsBadgeProps {
    conditions: OptimizationRuleCondition[];
}

export const ConditionsBadge = ({ conditions }: ConditionsBadgeProps) => {
    if (!conditions || conditions.length === 0) {
        return (
            <div className="flex items-center gap-2 text-gray-400 dark:text-gray-600">
                <Filter className="h-4 w-4" />
                <span className="text-sm">No conditions</span>
            </div>
        );
    }

    const operatorLabels: Record<string, string> = {
        greater_than: '>',
        less_than: '<',
        equal: '=',
        greater_than_or_equal: '≥',
        less_than_or_equal: '≤',
    };

    return (
        <div className="flex flex-wrap gap-1.5">
            {conditions.map((condition, idx) => (
                <span
                    key={idx}
                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 whitespace-nowrap"
                >
                    <span className="font-semibold">{condition.metric.charAt(0).toUpperCase() + condition.metric.slice(1)}</span>
                    <span className="opacity-75">{operatorLabels[condition.operator] || condition.operator}</span>
                    <span className="font-semibold">{condition.value}</span>
                </span>
            ))}
        </div>
    );
};

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { OPERATOR_OPTIONS, type FormState } from '../types/metric-filters';

interface MetricFilterFormProps {
    formState: FormState;
    selectedMetrics: string[];
    onFormStateChange: (state: FormState) => void;
    onApply: () => void;
    onCancel: () => void;
    variant?: 'add' | 'edit';
}

export const MetricFilterForm = ({
    formState,
    selectedMetrics,
    onFormStateChange,
    onApply,
    onCancel,
    variant = 'add',
}: MetricFilterFormProps) => {
    const isValid = formState.metric && formState.value;
    const bgColor = variant === 'edit'
        ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800'
        : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-800';

    return (
        <div className={`flex flex-col sm:flex-row sm:items-end gap-3 p-3 border rounded-lg ${bgColor}`}>
            {/* Metric Dropdown */}
            <div className="flex flex-col gap-1 flex-1 sm:flex-none">
                <label className="text-xs font-medium text-gray-600 dark:text-gray-400">Metric</label>
                <select
                    value={formState.metric}
                    onChange={(e) => onFormStateChange({ ...formState, metric: e.target.value })}
                    className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
                >
                    <option value="">Select metric...</option>
                    {selectedMetrics.map(metric => (
                        <option key={metric} value={metric}>
                            {metric.charAt(0).toUpperCase() + metric.slice(1)}
                        </option>
                    ))}
                </select>
            </div>

            {/* Operator Dropdown */}
            <div className="flex flex-col gap-1 flex-1 sm:flex-none">
                <label className="text-xs font-medium text-gray-600 dark:text-gray-400">Condition</label>
                <select
                    value={formState.operator}
                    onChange={(e) => onFormStateChange({ ...formState, operator: e.target.value })}
                    className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
                >
                    {OPERATOR_OPTIONS.map(opt => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            </div>

            {/* Value Input */}
            <div className="flex flex-col gap-1 flex-1 sm:flex-none">
                <label className="text-xs font-medium text-gray-600 dark:text-gray-400">Value</label>
                <Input
                    type="number"
                    step="0.01"
                    placeholder="Enter value"
                    value={formState.value}
                    onChange={(e) => onFormStateChange({ ...formState, value: e.target.value })}
                    className="h-9"
                />
            </div>

            {/* Action Buttons */}
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={onApply}
                    disabled={!isValid}
                    className="h-9"
                >
                    {variant === 'edit' ? 'Save' : 'Apply'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onCancel}
                    className="h-9"
                >
                    Cancel
                </Button>
            </div>
        </div>
    );
};

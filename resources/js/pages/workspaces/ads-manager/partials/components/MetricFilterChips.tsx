import { X } from 'lucide-react';
import { type MetricFilter, getOperatorSymbol } from '../types/metric-filters';

interface MetricFilterChipsProps {
    filters: MetricFilter[];
    onEdit: (index: number) => void;
    onRemove: (index: number) => void;
}

export const MetricFilterChips = ({
    filters,
    onEdit,
    onRemove,
}: MetricFilterChipsProps) => {
    if (filters.length === 0) return null;

    return (
        <div className="flex flex-wrap gap-2">
            {filters.map((filter, index) => (
                <div
                    key={index}
                    className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-full hover:shadow-md transition-shadow cursor-pointer"
                    onClick={() => onEdit(index)}
                >
                    <span className="text-sm text-gray-700 dark:text-gray-300">
                        <span className="font-medium capitalize">{filter.metric}</span>
                        {' '}
                        <span className="text-gray-600 dark:text-gray-400">
                            {getOperatorSymbol(filter.operator)}
                        </span>
                        {' '}
                        <span className="font-medium">{filter.value}</span>
                    </span>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            onRemove(index);
                        }}
                        className="text-gray-400 hover:text-red-600 transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ))}
        </div>
    );
};

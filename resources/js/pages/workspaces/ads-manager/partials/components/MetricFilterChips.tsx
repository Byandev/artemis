import { Badge } from '@/components/ui/badge';
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
                <Badge
                    key={index}
                    variant="secondary"
                    className="cursor-pointer transition-shadow hover:brightness-95 gap-2 p-2"
                    onClick={() => onEdit(index)}
                >
                    <span>
                        <span className="font-medium capitalize">{filter.metric}</span>
                        {' '}
                        <span>
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
                        className="text-gray-400 hover:text-red-600 transition-colors ml-1"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </Badge>
            ))}
        </div>
    );
};

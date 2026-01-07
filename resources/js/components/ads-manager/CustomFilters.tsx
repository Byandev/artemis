import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { X, Plus } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface FilterCondition {
    id: string;
    metric: string;
    operator: string;
    value: string;
}

interface CustomFiltersProps {
    conditions: FilterCondition[];
    onConditionsChange: (conditions: FilterCondition[]) => void;
}

const METRICS = [
    { value: 'impressions', label: 'Impressions' },
    { value: 'clicks', label: 'Clicks' },
    { value: 'conversions', label: 'Conversions' },
    { value: 'spend', label: 'Spend' },
    { value: 'reach', label: 'Reach' },
    { value: 'ctr', label: 'CTR (Click-through rate)' },
    { value: 'cpc', label: 'CPC (Cost per click)' },
    { value: 'cpm', label: 'CPM (Cost per 1000 impressions)' },
    { value: 'cost_per_result', label: 'Cost per result' },
];

const OPERATORS = [
    { value: 'gt', label: 'is greater than' },
    { value: 'gte', label: 'is greater than or equal to' },
    { value: 'lt', label: 'is less than' },
    { value: 'lte', label: 'is less than or equal to' },
    { value: 'eq', label: 'is equal to' },
    { value: 'neq', label: 'is not equal to' },
];

export function CustomFilters({ conditions, onConditionsChange }: CustomFiltersProps) {
    const addCondition = () => {
        const newCondition: FilterCondition = {
            id: Math.random().toString(36).substr(2, 9),
            metric: '',
            operator: '',
            value: '',
        };
        onConditionsChange([...conditions, newCondition]);
    };

    const removeCondition = (id: string) => {
        onConditionsChange(conditions.filter(c => c.id !== id));
    };

    const updateCondition = (id: string, field: keyof FilterCondition, value: string) => {
        onConditionsChange(
            conditions.map(c => (c.id === id ? { ...c, [field]: value } : c))
        );
    };

    return (
        <div className="space-y-3">
            {conditions.length > 0 && (
                <div className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    <strong>Conditions:</strong> All of the following match. Note that some Ad Metrics can be delayed and would fluctuate for hours.
                </div>
            )}

            {conditions.map((condition) => (
                <div key={condition.id} className="flex items-center gap-2">
                    <Select
                        value={condition.metric}
                        onValueChange={(value) => updateCondition(condition.id, 'metric', value)}
                    >
                        <SelectTrigger className="w-[200px] h-9 text-sm">
                            <SelectValue placeholder="Select metric" />
                        </SelectTrigger>
                        <SelectContent>
                            {METRICS.map((metric) => (
                                <SelectItem key={metric.value} value={metric.value}>
                                    {metric.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={condition.operator}
                        onValueChange={(value) => updateCondition(condition.id, 'operator', value)}
                    >
                        <SelectTrigger className="w-[200px] h-9 text-sm">
                            <SelectValue placeholder="Select operator" />
                        </SelectTrigger>
                        <SelectContent>
                            {OPERATORS.map((operator) => (
                                <SelectItem key={operator.value} value={operator.value}>
                                    {operator.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <input
                        type="number"
                        placeholder="Value"
                        value={condition.value}
                        onChange={(e) => updateCondition(condition.id, 'value', e.target.value)}
                        className="w-[120px] h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                    />

                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-9 w-9"
                        onClick={() => removeCondition(condition.id)}
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            ))}

            <Button
                variant="outline"
                size="sm"
                onClick={addCondition}
                className="h-9 text-sm"
            >
                <Plus className="h-4 w-4 mr-2" />
                Add Condition
            </Button>
        </div>
    );
}

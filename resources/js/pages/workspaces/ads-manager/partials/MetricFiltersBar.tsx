import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { Input } from '@/components/ui/input';
import moment from 'moment';
import { useEffect, useRef, useState } from 'react';
import { type DateRange } from "react-day-picker";
import { Plus, Trash2 } from 'lucide-react';

interface MetricFilter {
    metric: string;
    operator: string;
    value: string;
}

interface MetricFiltersBarProps {
    searchValue: string;
    statusFilter: string;
    dateRange: DateRange | undefined;
    dateRangeStr: { from: string; to: string };
    selectedMetrics?: string[];
    availableMetrics?: string[];
    metricFilters: MetricFilter[];
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onDateRangeChange: (range: DateRange | undefined) => void;
    onMetricsChange?: (metrics: string[]) => void;
    onMetricFiltersChange: (filters: MetricFilter[]) => void;
    onClearFilters: () => void;
    searchPlaceholder?: string;
}

export const MetricFiltersBar = ({
    searchValue,
    statusFilter,
    dateRange,
    dateRangeStr,
    selectedMetrics = [],
    availableMetrics = [],
    metricFilters,
    onSearchChange,
    onStatusChange,
    onDateRangeChange,
    onMetricsChange,
    onMetricFiltersChange,
    onClearFilters,
    searchPlaceholder = "Search...",
}: MetricFiltersBarProps) => {
    const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);

    const hasActiveFilters = searchValue || statusFilter || metricFilters.length > 0 || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD');

    const toggleMetric = (metric: string) => {
        const updated = selectedMetrics.includes(metric)
            ? selectedMetrics.filter(m => m !== metric)
            : [...selectedMetrics, metric];
        onMetricsChange?.(updated);
    };

    const addMetricFilter = () => {
        onMetricFiltersChange([...metricFilters, { metric: 'impressions', operator: 'greater_than', value: '' }]);
    };

    const removeMetricFilter = (index: number) => {
        onMetricFiltersChange(metricFilters.filter((_, i) => i !== index));
    };

    const updateMetricFilter = (index: number, field: keyof MetricFilter, value: string) => {
        const updated = [...metricFilters];
        updated[index] = { ...updated[index], [field]: value };
        onMetricFiltersChange(updated);
    };

    return (
        <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 dark:border-white/5">
            {/* Search and Status Row */}
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <input
                    className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                    placeholder={searchPlaceholder}
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />

                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 relative z-50">
                    <select
                        value={statusFilter}
                        onChange={(e) => onStatusChange(e.target.value)}
                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                    >
                        <option value="">All Status</option>
                        <option value="ACTIVE">Active</option>
                        <option value="PAUSED">Paused</option>
                        <option value="ARCHIVED">Archived</option>
                    </select>

                    <SimpleDateRangePicker
                        value={dateRange}
                        onChange={onDateRangeChange}
                    />

                    {availableMetrics.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    Metrics {selectedMetrics.length > 0 && `(${selectedMetrics.length})`}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>Select Metrics</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {availableMetrics.map(metric => (
                                    <DropdownMenuCheckboxItem
                                        key={metric}
                                        checked={selectedMetrics.includes(metric)}
                                        onCheckedChange={() => toggleMetric(metric)}
                                    >
                                        <span className="capitalize">{metric}</span>
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {hasActiveFilters && (
                        <Button variant="outline" onClick={onClearFilters} size="sm">
                            Clear All
                        </Button>
                    )}
                </div>
            </div>

            {/* Metric Filters Section */}
            {metricFilters.length > 0 && (
                <div className="border-t border-gray-200 pt-3">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Metric Filters</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addMetricFilter}
                            className="h-8"
                        >
                            <Plus className="h-3.5 w-3.5 mr-1" />
                            Add Filter
                        </Button>
                    </div>

                    <div className="flex flex-col gap-2">
                        {metricFilters.map((filter, index) => (
                            <div
                                key={index}
                                className="flex flex-col sm:flex-row sm:items-center gap-2 p-2.5 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg"
                            >
                                <div className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide shrink-0">
                                    {index === 0 ? 'Where' : 'And'}
                                </div>

                                {/* Metric */}
                                <select
                                    value={filter.metric}
                                    onChange={(e) => updateMetricFilter(index, 'metric', e.target.value)}
                                    className="h-8 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 flex-1 sm:flex-none"
                                >
                                    <option value="impressions">Impressions</option>
                                    <option value="clicks">Clicks</option>
                                    <option value="spend">Spend</option>
                                </select>

                                {/* Operator */}
                                <select
                                    value={filter.operator}
                                    onChange={(e) => updateMetricFilter(index, 'operator', e.target.value)}
                                    className="h-8 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 flex-1 sm:flex-none"
                                >
                                    <option value="greater_than">Greater Than</option>
                                    <option value="less_than">Less Than</option>
                                    <option value="equal">Equal</option>
                                    <option value="greater_than_or_equal">Greater Than Or Equal</option>
                                    <option value="less_than_or_equal">Less Than Or Equal</option>
                                </select>

                                {/* Value */}
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={filter.value}
                                    onChange={(e) => updateMetricFilter(index, 'value', e.target.value)}
                                    placeholder="Value"
                                    className="h-8 w-full sm:w-24"
                                />

                                {/* Remove Button */}
                                {metricFilters.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeMetricFilter(index)}
                                        className="h-8 px-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Add First Metric Filter */}
            {metricFilters.length === 0 && (
                <div className="flex items-center gap-2 pt-2 border-t border-gray-200">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addMetricFilter}
                        className="h-8"
                    >
                        <Plus className="h-3.5 w-3.5 mr-1" />
                        Add Metric Filter
                    </Button>
                </div>
            )}
        </div>
    );
};

import moment from 'moment';
import { type DateRange } from 'react-day-picker';

import { MetricFilterSection } from './components/MetricFilterSection';
import { SearchAndFiltersRow } from './components/SearchAndFiltersRow';
import { useMetricFilters } from './hooks/useMetricFilters';
import { type MetricFilter } from './types/metric-filters';

export interface MetricFiltersBarProps {
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
    const {
        formState,
        setFormState,
        isFormOpen,
        setIsFormOpen,
        editingIndex,
        handleApplyFilter,
        handleCancelFilter,
        removeMetricFilter,
        startEditFilter,
        handleSaveEdit,
        handleCancelEdit,
    } = useMetricFilters(metricFilters, onMetricFiltersChange);

    const hasActiveFilters = !!(searchValue || statusFilter || metricFilters.length > 0 || selectedMetrics.length > 0 || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD'));

    return (
        <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 dark:border-white/5">
            <SearchAndFiltersRow
                searchValue={searchValue}
                statusFilter={statusFilter}
                dateRange={dateRange}
                selectedMetrics={selectedMetrics}
                availableMetrics={availableMetrics}
                hasActiveFilters={hasActiveFilters}
                searchPlaceholder={searchPlaceholder}
                onSearchChange={onSearchChange}
                onStatusChange={onStatusChange}
                onDateRangeChange={onDateRangeChange}
                onMetricsChange={onMetricsChange || (() => { })}
                onClearFilters={onClearFilters}
            />

            <MetricFilterSection
                selectedMetrics={selectedMetrics}
                metricFilters={metricFilters}
                formState={formState}
                isFormOpen={isFormOpen}
                editingIndex={editingIndex}
                onFormStateChange={setFormState}
                onOpenForm={() => setIsFormOpen(true)}
                onApplyFilter={handleApplyFilter}
                onCancelFilter={handleCancelFilter}
                onEditFilter={startEditFilter}
                onSaveEdit={handleSaveEdit}
                onCancelEdit={handleCancelEdit}
                onRemoveFilter={removeMetricFilter}
            />
        </div>
    );
};

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
import moment from 'moment';
import { useEffect, useRef } from 'react';
import { type DateRange } from "react-day-picker";

interface FiltersBarProps {
    searchValue: string;
    statusFilter: string;
    impressionsGreaterThan: string;
    clicksGreaterThan: string;
    spendGreaterThan: string;
    dailyBudgetGreaterThan: string;
    dateRange: DateRange | undefined;
    dateRangeStr: { from: string; to: string };
    selectedMetrics?: string[];
    availableMetrics?: string[];
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onImpressionsChange: (value: string) => void;
    onClicksChange: (value: string) => void;
    onSpendChange: (value: string) => void;
    onDailyBudgetChange: (value: string) => void;
    onDateRangeChange: (range: DateRange | undefined) => void;
    onMetricsChange?: (metrics: string[]) => void;
    onNumericFilterChange: () => void;
    onClearFilters: () => void;
    searchPlaceholder?: string;
    showClearButton?: boolean;
}

export const FiltersBar = ({
    searchValue,
    statusFilter,
    impressionsGreaterThan,
    clicksGreaterThan,
    spendGreaterThan,
    dailyBudgetGreaterThan,
    dateRange,
    dateRangeStr,
    selectedMetrics = [],
    availableMetrics = [],
    onSearchChange,
    onStatusChange,
    onImpressionsChange,
    onClicksChange,
    onSpendChange,
    onDailyBudgetChange,
    onDateRangeChange,
    onMetricsChange,
    onNumericFilterChange,
    onClearFilters,
    searchPlaceholder = "Search...",
    showClearButton = true,
}: FiltersBarProps) => {
    const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);

    // Debounce numeric filter changes
    useEffect(() => {
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        debounceTimerRef.current = setTimeout(() => {
            onNumericFilterChange();
        }, 500);

        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, [impressionsGreaterThan, clicksGreaterThan, spendGreaterThan, dailyBudgetGreaterThan, onNumericFilterChange]);

    const hasActiveFilters = searchValue || statusFilter || impressionsGreaterThan || clicksGreaterThan || spendGreaterThan || dailyBudgetGreaterThan || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD');

    const toggleMetric = (metric: string) => {
        const updated = selectedMetrics.includes(metric)
            ? selectedMetrics.filter(m => m !== metric)
            : [...selectedMetrics, metric];

        // Reset the corresponding filter when toggling
        if (metric === 'impressions') {
            onImpressionsChange('');
        } else if (metric === 'clicks') {
            onClicksChange('');
        } else if (metric === 'spend') {
            onSpendChange('');
        }

        onMetricsChange?.(updated);
    };

    return (
        <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/5">
            <input
                className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => onSearchChange(e.target.value)}
            />
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 relative z-50">
                {showClearButton && hasActiveFilters && (
                    <Button
                        variant="outline"
                        onClick={onClearFilters}
                    >
                        Clear Filters
                    </Button>
                )}
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
                            {selectedMetrics.length > 0 && (
                                <>
                                    <DropdownMenuSeparator />
                                    <div className="px-2 py-1.5 space-y-2">
                                        {selectedMetrics.includes('impressions') && (
                                            <input
                                                type="number"
                                                placeholder="Min Impressions"
                                                value={impressionsGreaterThan}
                                                onChange={(e) => onImpressionsChange(e.target.value)}
                                                className="w-full h-8 rounded border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                            />
                                        )}
                                        {selectedMetrics.includes('clicks') && (
                                            <input
                                                type="number"
                                                placeholder="Min Clicks"
                                                value={clicksGreaterThan}
                                                onChange={(e) => onClicksChange(e.target.value)}
                                                className="w-full h-8 rounded border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                            />
                                        )}
                                        {selectedMetrics.includes('spend') && (
                                            <input
                                                type="number"
                                                placeholder="Min Spend"
                                                value={spendGreaterThan}
                                                onChange={(e) => onSpendChange(e.target.value)}
                                                className="w-full h-8 rounded border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                            />
                                        )}
                                    </div>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </div>
    );
};

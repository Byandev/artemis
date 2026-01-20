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
import { type DateRange } from 'react-day-picker';

interface SearchAndFiltersRowProps {
    searchValue: string;
    statusFilter: string;
    dateRange: DateRange | undefined;
    selectedMetrics: string[];
    availableMetrics: string[];
    hasActiveFilters: boolean;
    searchPlaceholder: string;
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onDateRangeChange: (range: DateRange | undefined) => void;
    onMetricsChange: (metrics: string[]) => void;
    onClearFilters: () => void;
}

export const SearchAndFiltersRow = ({
    searchValue,
    statusFilter,
    dateRange,
    selectedMetrics,
    availableMetrics,
    hasActiveFilters,
    searchPlaceholder,
    onSearchChange,
    onStatusChange,
    onDateRangeChange,
    onMetricsChange,
    onClearFilters,
}: SearchAndFiltersRowProps) => {
    const toggleMetric = (metric: string) => {
        const updated = selectedMetrics.includes(metric)
            ? selectedMetrics.filter(m => m !== metric)
            : [...selectedMetrics, metric];
        onMetricsChange(updated);
    };

    return (
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <input
                className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20"
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => onSearchChange(e.target.value)}
            />

            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 relative z-50">
                {hasActiveFilters && (
                    <Button variant="outline" onClick={onClearFilters} size="sm">
                        Clear All
                    </Button>
                )}

                <select
                    value={statusFilter}
                    onChange={(e) => onStatusChange(e.target.value)}
                    className="h-9 rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300"
                >
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="PAUSED">Paused</option>
                    <option value="ARCHIVED">Archived</option>
                </select>

                <SimpleDateRangePicker
                    value={dateRange}
                    onChange={onDateRangeChange}
                    useGlobalState
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
            </div>
        </div>
    );
};

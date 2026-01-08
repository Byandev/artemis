import { Button } from '@/components/ui/button';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { type DateRange } from 'react-day-picker';

interface OptimizationRulesFiltersProps {
    searchValue: string;
    onSearchChange: (value: string) => void;
    statusFilter: string;
    onStatusChange: (value: string) => void;
    dateRange: DateRange | undefined;
    onDateRangeChange: (range: DateRange | undefined) => void;
    onAddRule: () => void;
}

const OptimizationRulesFilters = ({
    searchValue,
    onSearchChange,
    statusFilter,
    onStatusChange,
    dateRange,
    onDateRangeChange,
    onAddRule,
}: OptimizationRulesFiltersProps) => {
    return (
        <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/5">
            <input
                className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                placeholder="Search optimization rules..."
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
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                </select>
                <SimpleDateRangePicker
                    value={dateRange}
                    onChange={onDateRangeChange}
                />
                <Button
                    variant="default"
                    size="sm"
                    onClick={onAddRule}
                    className="w-full sm:w-auto"
                >
                    <span className="hidden sm:inline">Add New Rule</span>
                    <span className="sm:hidden">Add Rule</span>
                </Button>
            </div>
        </div>
    );
};

export default OptimizationRulesFilters;

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

interface SearchAndFiltersRowProps {
    searchValue: string;
    statusFilter: string;
    selectedMetrics: string[];
    availableMetrics: string[];
    hasActiveFilters: boolean;
    searchPlaceholder: string;
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onMetricsChange: (metrics: string[]) => void;
    onClearFilters: () => void;
}

export const SearchAndFiltersRow = ({
    searchValue,
    statusFilter,
    selectedMetrics,
    availableMetrics,
    hasActiveFilters,
    searchPlaceholder,
    onSearchChange,
    onStatusChange,
    onMetricsChange,
    onClearFilters,
}: SearchAndFiltersRowProps) => {
    const toggleMetric = (metric: string) => {
        const updated = selectedMetrics.includes(metric)
            ? selectedMetrics.filter((m) => m !== metric)
            : [...selectedMetrics, metric];
        onMetricsChange(updated);
    };

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <input
                className="w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden sm:px-4 sm:py-2.5 lg:max-w-sm"
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => onSearchChange(e.target.value)}
            />

            <div className="relative z-50 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                {hasActiveFilters && (
                    <Button
                        variant="outline"
                        onClick={onClearFilters}
                        size="sm"
                    >
                        Clear All
                    </Button>
                )}

                <select
                    value={statusFilter}
                    onChange={(e) => onStatusChange(e.target.value)}
                    className="h-9 rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/20 focus:outline-hidden"
                >
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="PAUSED">Paused</option>
                    <option value="ARCHIVED">Archived</option>
                </select>

                <SimpleDateRangePicker useGlobalState />

                {availableMetrics.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">
                                Metrics{' '}
                                {selectedMetrics.length > 0 &&
                                    `(${selectedMetrics.length})`}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>
                                Select Metrics
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {availableMetrics.map((metric) => (
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

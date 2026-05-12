import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, X } from 'lucide-react';

interface OrderFiltersProps {
    pageNameSearch: string;
    customerFilter: string;
    riderFilter: string;
    customers: string[];
    riders: string[];
    onPageNameChange: (value: string) => void;
    onFilterChange: (filterType: 'customer' | 'rider', value: string) => void;
    onClearFilters: () => void;
}

const OrderFilters = ({
    pageNameSearch,
    customerFilter,
    riderFilter,
    customers,
    riders,
    onPageNameChange,
    onFilterChange,
    onClearFilters,
}: OrderFiltersProps) => {
    return (
        <div className="mb-8 flex flex-col items-start justify-between gap-4">
            <div className="flex w-1/2 flex-col gap-4 sm:flex-row sm:items-end">
                <div className="flex-1">
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search page..."
                            value={pageNameSearch}
                            onChange={(e) => onPageNameChange(e.target.value)}
                            className="pl-8"
                        />
                        {pageNameSearch !== '' && (
                            <button
                                onClick={() => onPageNameChange('')}
                                className="absolute top-2.5 right-2.5 h-4 w-4 text-muted-foreground"
                                aria-label="Clear search"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </div>

                {(pageNameSearch || customerFilter || riderFilter) && (
                    <Button onClick={onClearFilters} variant="outline">
                        Clear Filters
                    </Button>
                )}
            </div>
        </div>
    );
};

export default OrderFilters;

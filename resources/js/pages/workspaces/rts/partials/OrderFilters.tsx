import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'

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
    onClearFilters
}: OrderFiltersProps) => {
    return (
        <div className='flex flex-col items-start justify-between gap-4 mb-8'>
            <div className="flex flex-col gap-4 w-1/2 sm:flex-row sm:items-end">
                <div className="flex-1 space-y-2">
                    <label className="text-sm font-medium">Search Page Name</label>
                    <Input
                        placeholder="Search by page name..."
                        value={pageNameSearch}
                        onChange={(e) => onPageNameChange(e.target.value)}
                    />
                </div>
                <div className="flex-1 space-y-2">
                    <label className="text-sm font-medium">Customer</label>
                    <Select
                        value={customerFilter || undefined}
                        onValueChange={(value) => onFilterChange('customer', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="All customers" />
                        </SelectTrigger>
                        <SelectContent>
                            {customers.map((customer) => (
                                <SelectItem key={customer} value={customer}>
                                    {customer}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex-1 space-y-2">
                    <label className="text-sm font-medium">Rider</label>
                    <Select
                        value={riderFilter || undefined}
                        onValueChange={(value) => onFilterChange('rider', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="All riders" />
                        </SelectTrigger>
                        <SelectContent>
                            {riders.map((rider) => (
                                <SelectItem key={rider} value={rider}>
                                    {rider}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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

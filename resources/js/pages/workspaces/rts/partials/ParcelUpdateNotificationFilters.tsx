import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Search, X } from 'lucide-react';

interface ParcelUpdateNotificationFiltersProps {
    pageNameSearch: string;
    typeFilter: string;
    types: string[];
    onPageNameChange: (value: string) => void;
    onTypeFilterChange: (value: string) => void;
    onClearFilters: () => void;
}

export default function ParcelUpdateNotificationFilters({
    pageNameSearch,
    typeFilter,
    types,
    onPageNameChange,
    onTypeFilterChange,
    onClearFilters,
}: ParcelUpdateNotificationFiltersProps) {
    const hasActiveFilters = pageNameSearch !== '' || typeFilter !== '';

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

                <div className="flex-1">
                    <Select
                        value={typeFilter === '' ? 'all' : typeFilter}
                        onValueChange={(value) =>
                            onTypeFilterChange(value === 'all' ? '' : value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            {types.map((type) => (
                                <SelectItem key={type} value={type}>
                                    <span className="capitalize">{type}</span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {hasActiveFilters && (
                    <Button variant="outline" onClick={onClearFilters}>
                        Clear Filters
                    </Button>
                )}
            </div>
        </div>
    );
}

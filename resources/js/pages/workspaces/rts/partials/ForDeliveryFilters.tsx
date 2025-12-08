import React from 'react'
import { Loader2, Search, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'

interface Filters {
    page_name: string;
    customer: string;
    rider: string;
}

interface ForDeliveryFiltersProps {
    pageName: string;
    setPageName: (v: string) => void;
    filters: Filters;
    setFilters: (f: Filters) => void;
    applyFilters: (f: Filters) => void;
    clearFilters: () => void;
    customers: string[];
    riders: string[];
    isLoading: boolean;
}

export default function ForDeliveryFilters({ pageName, setPageName, filters, setFilters, applyFilters, clearFilters, customers, riders, isLoading }: ForDeliveryFiltersProps) {
    return (
        <div className="flex items-center gap-2">
            <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    type="text"
                    placeholder="Search page..."
                    value={pageName}
                    onChange={(e) => setPageName(e.target.value)}
                    className="pl-8 w-[200px]"
                    disabled={isLoading}
                />

                {/* show spinner when loading and there's a search value, otherwise show clear button when pageName exists */}
                {(isLoading && pageName !== '') ? (
                    <span className="absolute right-2 top-1/2 -translate-y-1/2">
                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                    </span>
                ) : (
                    pageName !== '' && (
                        <button
                            onClick={() => setPageName('')}
                            className="absolute right-2.5 top-2.5 h-4 w-4 text-muted-foreground"
                            aria-label="Clear search"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )
                )}
            </div>

            <Select
                value={(filters.customer === '' || filters.customer === 'all') ? 'all' : filters.customer}
                onValueChange={(value: string) => {
                    const val = value === 'all' ? '' : value;
                    const newFilters = { ...filters, customer: val };
                    setFilters(newFilters);
                    applyFilters(newFilters);
                }}
                disabled={isLoading}
            >
                <SelectTrigger className="px-3 py-2 border rounded-md w-56">
                    <SelectValue placeholder="All Customers" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Customers</SelectItem>
                    {customers.length > 0 && customers.map((c, i) => (
                        <SelectItem key={i} value={c}>
                            {c}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={(filters.rider === '' || filters.rider === 'all') ? 'all' : filters.rider}
                onValueChange={(value: string) => {
                    const val = value === 'all' ? '' : value;
                    const newFilters = { ...filters, rider: val };
                    setFilters(newFilters);
                    applyFilters(newFilters);
                }}
                disabled={isLoading}
            >
                <SelectTrigger className="px-3 py-2 border rounded-md w-56">
                    <SelectValue placeholder="All Riders" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Riders</SelectItem>
                    {riders.length > 0 && riders.map((r, i) => (
                        <SelectItem key={i} value={r}>
                            {r}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {(
                pageName !== '' ||
                (filters.customer !== '' && filters.customer !== 'all') ||
                (filters.rider !== '' && filters.rider !== 'all')
            ) && (
                    <Button variant="outline" onClick={clearFilters} disabled={isLoading}>
                        Clear Filters
                    </Button>
                )}
        </div>
    )
}

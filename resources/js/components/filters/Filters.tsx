import { useState, useCallback, useMemo } from 'react';
import { SlidersHorizontal, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Badge } from '@/components/ui/badge'; // You may need to create this
import { Workspace } from '@/types/models/Workspace';
import ShopFilter from '@/components/filters/ShopFilter';
import PageFilter from '@/components/filters/PageFilter';

export interface FilterValue {
    teamIds: (string | number)[];
    productIds: (string | number)[];
    shopIds: (string | number)[];
    pageIds: (string | number)[];
    userIds: (string | number)[];
}

interface Props {
    workspace: Workspace;
    onChange: (value: FilterValue) => void;
    initialValue?: FilterValue; // Allow external control
    onClear?: () => void; // Optional clear callback
}

const INITIAL_FILTER_VALUE: FilterValue = {
    teamIds: [],
    productIds: [],
    shopIds: [],
    pageIds: [],
    userIds: [],
};

const Filters = ({
    workspace,
    onChange,
    initialValue = INITIAL_FILTER_VALUE,
    onClear,
}: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<FilterValue>(initialValue);
    const [hasChanges, setHasChanges] = useState(false);

    // Check if any filters are active
    const hasActiveFilters = useMemo(() => {
        return Object.values(localValue).some((arr) => arr.length > 0);
    }, [localValue]);

    // Handle filter changes efficiently
    const handleFilterChange = useCallback(
        (type: keyof FilterValue, id: string | number) => {
            setLocalValue((prev) => {
                const currentArray = prev[type];
                const newArray = currentArray.includes(id)
                    ? currentArray.filter((item) => item !== id)
                    : [...currentArray, id];

                setHasChanges(true);
                return {
                    ...prev,
                    [type]: newArray,
                };
            });
        },
        [],
    );

    // Handle apply button click
    const handleApply = useCallback(() => {
        onChange(localValue);
        setHasChanges(false);
        setIsOpen(false);
    }, [localValue, onChange]);

    // Handle clear all filters
    const handleClearAll = useCallback(() => {
        setLocalValue(INITIAL_FILTER_VALUE);
        setHasChanges(true);
        onClear?.();
    }, [onClear]);

    // Handle popover close without applying
    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open && hasChanges) {
                // Optionally show a confirmation dialog here
                const shouldDiscard = window.confirm('Discard changes?');
                if (shouldDiscard) {
                    setLocalValue(initialValue);
                    setHasChanges(false);
                } else {
                    setIsOpen(true);
                    return;
                }
            }
            setIsOpen(open);
        },
        [hasChanges, initialValue],
    );

    // Get active filter count for badge
    const activeFilterCount = useMemo(() => {
        return Object.values(localValue).reduce(
            (acc, arr) => acc + arr.length,
            0,
        );
    }, [localValue]);

    return (
        <Popover open={isOpen} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="default"
                    className="relative border  border-gray-300 gap-2 rounded-full px-4"
                >
                    <SlidersHorizontal className="h-4 w-4" />
                    Filters
                    {activeFilterCount > 0 && (
                        <Badge
                            variant="secondary"
                            className="ml-1 h-5 w-5 rounded-full p-0 text-xs"
                        >
                            {activeFilterCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] rounded-2xl p-4 sm:w-80"
                align="start"
            >
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium">Filters</h3>
                    {hasActiveFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleClearAll}
                            className="h-8 px-2 text-xs"
                        >
                            <X className="mr-1 h-3 w-3" />
                            Clear all
                        </Button>
                    )}
                </div>

                <div className="max-h-72 space-y-3 overflow-y-auto">
                    <PageFilter
                        workspace={workspace}
                        selected={localValue.pageIds}
                        onSelect={(id) => handleFilterChange('pageIds', id)}
                    />

                    <ShopFilter
                        workspace={workspace}
                        selected={localValue.shopIds}
                        onSelect={(id) => handleFilterChange('shopIds', id)}
                    />
                </div>

                <div className="mt-4 flex gap-2">
                    <Button
                        size="sm"
                        onClick={handleApply}
                        disabled={!hasChanges}
                        className="flex-1"
                    >
                        Apply
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setLocalValue(initialValue);
                            setHasChanges(false);
                            setIsOpen(false);
                        }}
                    >
                        Cancel
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default Filters;

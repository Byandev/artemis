import { Product } from '@/types/models/Product';

export const parseAmount = (value: string): number => {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

export const computeTotals = (cogAmount: string, deliveryFee: string) => {
    const numericCog = parseAmount(cogAmount);
    const numericDelivery = parseAmount(deliveryFee);
    const numericTotal = numericCog + numericDelivery;
    const shouldDisplay = cogAmount !== '' || deliveryFee !== '';

    return {
        numericCog,
        numericDelivery,
        numericTotal,
        displayTotal: shouldDisplay ? numericTotal.toFixed(2) : '',
    };
};

export const buildEmptyState = (
    rowsLength: number,
    loading: boolean,
    hasActiveFilters: boolean,
    forceDebugEmptyState: boolean,
) => {
    const isEffectivelyEmpty = forceDebugEmptyState || rowsLength === 0;
    const showEmptyState = !loading && isEffectivelyEmpty;
    const useFilteredEmptyCopy = hasActiveFilters;
    const emptyStateTitle = useFilteredEmptyCopy ? 'No PO Records found' : 'No records yet';
    const emptyStateDescription = useFilteredEmptyCopy
        ? 'Try adjusting your search or selected period'
        : 'There are no orders available yet. New records will appear here once created.';
    const emptyStateButtonLabel = useFilteredEmptyCopy ? 'Add Item' : 'Create Item';

    return { showEmptyState, emptyStateTitle, emptyStateDescription, emptyStateButtonLabel };
};

export const buildItemOptions = (products: Product[], currentItem: string) => {
    const names = new Set<string>();

    products.forEach((product) => {
        const label = (product.name || product.title || '').trim();
        if (label) names.add(label);
    });

    const currentValue = currentItem.trim();
    if (currentValue && !names.has(currentValue)) names.add(currentValue);

    return Array.from(names).sort((a, b) => a.localeCompare(b));
};

export const computeStatusFilterLabel = (statusFilter: string, labelFn: (value: string | number) => string) => {
    return statusFilter === 'all' ? 'All Status' : labelFn(statusFilter);
};

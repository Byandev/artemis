import { useCallback, useState } from 'react';

export interface RmoFilterQuery {
    sort?: string | null;
    filter?: {
        search?: string;
        status?: string | string[];
        page_id?: string | string[];
        shop_id?: string | string[];
        parcel_status?: string | string[];
    };
    page?: number;
    perPage?: number;
}

function parseFilterArray(v: string | string[] | undefined): string[] {
    if (!v) return [];
    return Array.isArray(v) ? v : v.split(',').filter(Boolean);
}

export function useRmoFilterState(query?: RmoFilterQuery) {
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatuses, setSelectedStatuses] = useState(() => parseFilterArray(query?.filter?.status));
    const [selectedPageIds, setSelectedPageIds] = useState(() => parseFilterArray(query?.filter?.page_id));
    const [selectedParcelStatuses, setSelectedParcelStatuses] = useState(() => parseFilterArray(query?.filter?.parcel_status));
    const [selectedShopIds, setSelectedShopIds] = useState(() => parseFilterArray(query?.filter?.shop_id));

    const hasActiveFilters = !!(
        searchValue ||
        selectedStatuses.length ||
        selectedPageIds.length ||
        selectedParcelStatuses.length ||
        selectedShopIds.length
    );

    const clearAllFilters = useCallback(() => {
        setSearchValue('');
        setSelectedStatuses([]);
        setSelectedPageIds([]);
        setSelectedParcelStatuses([]);
        setSelectedShopIds([]);
    }, []);

    const buildParams = useCallback(
        (sort?: string | null, page?: number): Record<string, string | number | undefined> => {
            const params: Record<string, string | number | undefined> = {
                sort: sort ?? undefined,
                'filter[search]': searchValue || undefined,
                page: page ?? 1,
            };
            if (selectedStatuses.length) params['filter[status]'] = selectedStatuses.join(',');
            if (selectedPageIds.length) params['filter[page_id]'] = selectedPageIds.join(',');
            if (selectedParcelStatuses.length) params['filter[parcel_status]'] = selectedParcelStatuses.join(',');
            if (selectedShopIds.length) params['filter[shop_id]'] = selectedShopIds.join(',');
            return params;
        },
        [searchValue, selectedStatuses, selectedPageIds, selectedParcelStatuses, selectedShopIds],
    );

    return {
        searchValue,
        setSearchValue,
        selectedStatuses,
        setSelectedStatuses,
        selectedPageIds,
        setSelectedPageIds,
        selectedParcelStatuses,
        setSelectedParcelStatuses,
        selectedShopIds,
        setSelectedShopIds,
        hasActiveFilters,
        clearAllFilters,
        buildParams,
    };
}

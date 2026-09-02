import { toBackendSort, toFrontendSort } from '@/lib/sort';
import type { PaginationState, SortingState } from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';
import { EMPTY_FILTERS, LedgerFilterValue } from './ledger-filters';
import { EntryType } from './types';

export interface LedgerUrlState {
    filters: LedgerFilterValue;
    sorting: SortingState;
    pagination: PaginationState;
}

const DEFAULT_PAGE_SIZE = 25;

/** Query-string keys, kept short so a shared link stays readable. */
const KEYS = {
    search: 'q',
    type: 'side',
    transactionType: 'txn',
    department: 'dept',
    from: 'from',
    to: 'to',
    sort: 'sort',
    page: 'page',
    perPage: 'per',
} as const;

const EMPTY_STATE: LedgerUrlState = {
    filters: EMPTY_FILTERS,
    sorting: [],
    pagination: { pageIndex: 0, pageSize: DEFAULT_PAGE_SIZE },
};

/** Read the ledger's view state back out of the current URL. */
function readUrl(): LedgerUrlState {
    // Rendered on the server too, where there is no URL to read.
    if (typeof window === 'undefined') return EMPTY_STATE;

    const params = new URLSearchParams(window.location.search);
    const get = (key: string) => params.get(key) ?? '';

    const side = get(KEYS.type);
    const page = Number(get(KEYS.page));
    const perPage = Number(get(KEYS.perPage));

    return {
        filters: {
            search: get(KEYS.search),
            type: side === 'in' || side === 'out' ? (side as EntryType) : '',
            transactionType: get(KEYS.transactionType),
            department: get(KEYS.department),
            from: get(KEYS.from),
            to: get(KEYS.to),
        },
        sorting: toFrontendSort(get(KEYS.sort)),
        pagination: {
            pageIndex: Number.isFinite(page) && page > 1 ? page - 1 : 0,
            pageSize:
                Number.isFinite(perPage) && perPage > 0
                    ? perPage
                    : DEFAULT_PAGE_SIZE,
        },
    };
}

function writeUrl(state: LedgerUrlState): void {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);

    const set = (key: string, value: string) => {
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
    };

    set(KEYS.search, state.filters.search);
    set(KEYS.type, state.filters.type);
    set(KEYS.transactionType, state.filters.transactionType);
    set(KEYS.department, state.filters.department);
    set(KEYS.from, state.filters.from);
    set(KEYS.to, state.filters.to);
    set(KEYS.sort, toBackendSort(state.sorting));
    // Defaults stay out of the URL, so an untouched table has a clean address.
    set(
        KEYS.page,
        state.pagination.pageIndex > 0
            ? String(state.pagination.pageIndex + 1)
            : '',
    );
    set(
        KEYS.perPage,
        state.pagination.pageSize === DEFAULT_PAGE_SIZE
            ? ''
            : String(state.pagination.pageSize),
    );

    const query = params.toString();

    // replaceState, not an Inertia visit: the rows are already in memory, so
    // remembering the view must not cost a request or a history entry.
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${query ? `?${query}` : ''}`,
    );
}

/**
 * Keeps the ledger's search, filters, date range, sort and page in the URL, so
 * a reload — or a shared link — comes back to the same view. Nothing here talks
 * to the server.
 */
export function useLedgerUrlState() {
    const initial = useRef<LedgerUrlState | null>(null);
    if (!initial.current) initial.current = readUrl();

    const [filters, setFilters] = useState<LedgerFilterValue>(
        initial.current.filters,
    );
    const [sorting, setSorting] = useState<SortingState>(
        initial.current.sorting,
    );
    const [pagination, setPagination] = useState<PaginationState>(
        initial.current.pagination,
    );

    useEffect(() => {
        writeUrl({ filters, sorting, pagination });
    }, [filters, sorting, pagination]);

    return {
        filters,
        setFilters,
        sorting,
        setSorting,
        pagination,
        setPagination,
        initial: initial.current,
        resetFilters: () => setFilters(EMPTY_FILTERS),
        defaultPageSize: DEFAULT_PAGE_SIZE,
    };
}

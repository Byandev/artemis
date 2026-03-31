import Pagination from '@/components/ui/pagination';

interface PurchasedOrdersPaginationProps {
    loading: boolean;
    hasPrevious: boolean;
    hasNext: boolean;
    fromRow: number;
    toRow: number;
    totalRows: number;
    currentPage: number;
    lastPage: number;
    paginationPages: number[];
    onFetchPage: (page: number) => void;
}

export function PurchasedOrdersPagination({
    loading,
    fromRow,
    toRow,
    totalRows,
    currentPage,
    lastPage,
    onFetchPage,
}: PurchasedOrdersPaginationProps) {
    if (loading || totalRows === 0) {
        return null;
    }

    return (
        <div className="mt-3 rounded-[14px] border border-black/6 bg-white px-4 py-3 shadow-theme-xs dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <p className="font-mono text-[12px] font-light text-gray-500 dark:text-gray-400">
                    Showing {fromRow} to {toRow} of {totalRows} entries
                </p>

                <Pagination currentPage={currentPage} totalPages={lastPage} onPageChange={onFetchPage} />
            </div>
        </div>
    );
}

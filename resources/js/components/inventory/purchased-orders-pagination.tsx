import Pagination from '@/components/ui/pagination';

interface PurchasedOrdersPaginationProps {
    loading: boolean;
    fromRow: number;
    toRow: number;
    totalRows: number;
    currentPage: number;
    lastPage: number;
    onFetchPage: (page: number) => void;
    variant?: 'card' | 'inline';
}

export function PurchasedOrdersPagination({
    loading,
    fromRow,
    toRow,
    totalRows,
    currentPage,
    lastPage,
    onFetchPage,
    variant = 'card',
}: PurchasedOrdersPaginationProps) {
    if (loading || totalRows === 0) {
        return null;
    }

    if (variant === 'inline') {
        return (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    Showing {fromRow} to {toRow} of {totalRows} entries
                </p>

                <Pagination currentPage={currentPage} totalPages={lastPage} onPageChange={onFetchPage} />
            </div>
        );
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

import { useMemo } from 'react';

interface PaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (newPage: number) => void
}

const Pagination = ({ currentPage, totalPages , onPageChange}: PaginationProps) => {
    const pagesAroundCurrent = useMemo(() => {
        const windowSize = Math.min(3, totalPages);
        const start = Math.min(
            Math.max(currentPage - 1, 1),
            Math.max(totalPages - windowSize + 1, 1)
        );

        return Array.from({ length: windowSize }, (_, i) => start + i);
    }, [currentPage, totalPages]);

    return (
        <div className="flex items-center justify-center gap-2">
            <button
                onClick={() => onPageChange(currentPage - 1)}
                disabled={currentPage === 1}
                className="flex items-center h-8 justify-center rounded-lg border border-gray-100 bg-white px-3.5 py-2.5 text-gray-400 shadow-theme-xs hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] text-sm"
            >
                Previous
            </button>

            <div className="flex items-center gap-1">
                {pagesAroundCurrent[0] > 1 && <span className="px-2">...</span>}
                {pagesAroundCurrent.map((page) => (
                    <button
                        key={page}
                        onClick={() => onPageChange(page)}
                        className={`px-4 py-2 rounded ${
                            currentPage === page
                                ? "bg-brand-600 text-white"
                                : "text-gray-400 dark:text-gray-400 border"
                        } flex w-8 items-center justify-center h-8 rounded-lg text-xs font-medium hover:bg-blue-500/[0.08] hover:text-brand-500 dark:hover:text-brand-500`}
                    >
                        {page}
                    </button>
                ))}
                {pagesAroundCurrent[pagesAroundCurrent.length - 1] < totalPages && (
                    <span className="px-2 text-gray-400">...</span>
                )}
            </div>

            <button
                onClick={() => onPageChange(currentPage + 1)}
                disabled={currentPage === totalPages}
                className="flex items-center justify-center rounded-lg border border-gray-100 bg-white px-3.5 py-2.5 text-gray-400 shadow-theme-xs text-xs hover:bg-gray-50 h-8 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
            >
                Next
            </button>
        </div>
    )
}

export default Pagination
